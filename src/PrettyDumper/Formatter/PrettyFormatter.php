<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter;

use Anhoder\PrettyDumper\Context\Collectors\ContextCollector;
use Anhoder\PrettyDumper\Context\Collectors\DefaultContextCollector;
use Anhoder\PrettyDumper\Context\ContextFrame;
use Anhoder\PrettyDumper\Context\ContextSnapshot;
use Anhoder\PrettyDumper\Context\RedactionRule;
use Anhoder\PrettyDumper\Formatter\Support\PerformanceMonitor;
use Anhoder\PrettyDumper\Formatter\Transformers\ExceptionTransformer;
use Anhoder\PrettyDumper\Formatter\Transformers\JsonTransformer;
use Anhoder\PrettyDumper\Support\ThemeRegistry;
use Throwable;

final class PrettyFormatter
{
    private JsonTransformer $jsonTransformer;

    private ExceptionTransformer $exceptionTransformer;

    private ContextCollector $collector;

    private PerformanceMonitor $monitor;

    private int $arrayMarkerCounter = 0;

    private const ARRAY_MARKER = '__pretty_dumper_reference';

    public function __construct(
        private string $channel,
        private FormatterConfiguration $configuration,
        ?ContextCollector $collector = null,
        ?ThemeRegistry $registry = null,
    ) {
        $this->jsonTransformer = new JsonTransformer();
        $this->exceptionTransformer = new ExceptionTransformer();
        $this->collector = $collector ?? new DefaultContextCollector();
        $this->themes = $registry ?? ThemeRegistry::withDefaults();
        $this->monitor = new PerformanceMonitor();
    }

    public static function forChannel(
        string $channel,
        FormatterConfiguration $configuration,
        ?ContextCollector $collector = null,
        ?ThemeRegistry $registry = null,
    ): self {
        return new self($channel, $configuration, $collector, $registry);
    }

    public function configuration(): FormatterConfiguration
    {
        return $this->configuration;
    }

    public function format(DumpRenderRequest $request): RenderedSegment
    {
        $this->monitor->start();
        $contextInfo = $this->prepareContext($request);

        $root = new RenderedSegment('dump', sprintf('Dump (%s)', strtoupper($request->channel())), [
            'channel' => $request->channel(),
            'formatterChannel' => $this->channel,
            'theme' => $this->resolveTheme($request),
        ]);

        $seen = [];

        $payload = $request->payload();
        $expressionOption = $request->option('expression', '$value');
        $expression = is_string($expressionOption) ? $expressionOption : '$value';
        $payloadSegment = $this->renderValue(
            $payload,
            0,
            $request,
            $contextInfo['snapshot']->variables(),
            $seen,
            $expression,
        );
        $root->addChild($payloadSegment);

        if ($this->configuration->showContext()) {
            $root->addChild($this->renderContextSegment($contextInfo['snapshot'], $contextInfo['stackTruncated']));
        }

        $this->monitor->stop();

        if ($this->shouldRenderPerformanceSegment($request)) {
            $root->addChild($this->renderPerformanceSegment());
        }

        return $root;
    }

    private function shouldRenderPerformanceSegment(DumpRenderRequest $request): bool
    {
        $configured = $this->configuration->showPerformanceMetrics();

        if ($request->hasOption('showPerformanceMetrics')) {
            return (bool) $request->option('showPerformanceMetrics');
        }

        return $configured;
    }

    /**
     * @param array<string, mixed> $contextVariables
     * @param array<string, bool> $seen
     */
    private function renderValue(
        mixed &$value,
        int $depth,
        DumpRenderRequest $request,
        array $contextVariables,
        array &$seen,
        ?string $expression = null,
    ): RenderedSegment
    {
        if ($value instanceof Throwable) {
            $segment = $this->exceptionTransformer->transform(
                $value,
                $this->configuration,
                $this->configuration->redactionRules(),
                $contextVariables,
            );

            if ($expression !== null) {
                $segment = $segment->withMetadata('expression', $expression);
            }

            return $segment->withMetadata('jsonValue', null);
        }

        if (is_string($value) && $this->jsonTransformer->matches($value, $request)) {
            $segment = $this->jsonTransformer->transform($value, $this->configuration);

            if ($expression !== null) {
                $segment = $segment->withMetadata('expression', $expression);
            }

            $decoded = null;
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $decoded = null;
            }

            return $segment->withMetadata('jsonValue', $decoded);
        }

        if (is_array($value)) {
            $arrayId = $this->arrayReferenceId($value);
            if (isset($seen[$arrayId])) {
                $segment = new RenderedSegment('circular', '[circular reference]', [
                    'reference' => $arrayId,
                    'expression' => $expression,
                    'jsonValue' => '__circular_reference__',
                ]);
                $segment->setMetadata('truncated', true);
                $this->monitor->registerTruncation();

                return $segment;
            }

            $seen[$arrayId] = true;
            $result = $this->renderArray($value, $depth, $request, $contextVariables, $seen, $expression, 'array');
            unset($seen[$arrayId]);

            return $result;
        }

        if (is_string($value)) {
            return $this->renderString($value, $expression);
        }

        if (is_object($value)) {
            $objectId = $this->objectReferenceId($value);
            if (isset($seen[$objectId])) {
                $segment = new RenderedSegment('circular', '[circular object]', [
                    'reference' => $objectId,
                    'expression' => $expression,
                    'jsonValue' => '__circular_reference__',
                ]);
                $segment->setMetadata('truncated', true);
                $this->monitor->registerTruncation();

                return $segment;
            }

            $seen[$objectId] = true;
            $objectVars = $this->extractObjectProperties($value);
            $segment = $this->renderArray(
                $objectVars,
                $depth,
                $request,
                $contextVariables,
                $seen,
                $expression,
                'object',
                get_class($value),
            );
            unset($seen[$objectId]);

            return $segment;
        }

        if (is_bool($value)) {
            return RenderedSegment::leaf('bool', 'bool(' . ($value ? 'true' : 'false') . ')', [
                'expression' => $expression,
                'jsonValue' => $value,
            ]);
        }

        if ($value === null) {
            return RenderedSegment::leaf('null', 'null', [
                'expression' => $expression,
                'jsonValue' => null,
            ]);
        }

        if (is_int($value) || is_float($value)) {
            return RenderedSegment::leaf('number', sprintf('%s(%s)', get_debug_type($value), (string) $value), [
                'expression' => $expression,
                'jsonValue' => $value,
            ]);
        }

        return RenderedSegment::leaf('unknown', get_debug_type($value), [
            'expression' => $expression,
            'jsonValue' => null,
        ]);
    }

    /**
     * @param array<int|string, mixed> $values
     * @param array<string, mixed> $contextVariables
     * @param array<string, bool> $seen
     */
    private function renderArray(
        array &$values,
        int $depth,
        DumpRenderRequest $request,
        array $contextVariables,
        array &$seen,
        ?string $expression,
        string $pathType,
        ?string $objectClass = null,
    ): RenderedSegment
    {
        $count = count($values);
        if (isset($values[self::ARRAY_MARKER])) {
            $count--;
        }
        $segment = new RenderedSegment('array', sprintf('array(%d)', $count), [
            'expression' => $expression,
        ]);

        if ($depth >= $this->configuration->maxDepth()) {
            $segment->setMetadata('truncated', true);
            $segment->appendContent(' … truncated (depth limit)');
            $this->monitor->registerTruncation();
            $segment->setMetadata('jsonValue', $pathType === 'object'
                ? ['__class' => $objectClass, '__truncated__' => 'depth']
                : ['__truncated__' => 'depth']
            );

            return $segment;
        }

        $limit = min($this->configuration->maxItems(), $this->configuration->maxItemsHardLimit());
        $index = 0;
        $jsonResult = [];

        foreach ($values as $key => &$val) {
            if ($key === self::ARRAY_MARKER) {
                continue;
            }
            if ($index >= $limit) {
                $segment->setMetadata('truncated', true);
                $segment->addChild(RenderedSegment::leaf('notice', sprintf('… truncated (items: %d, limit: %d)', $count, $limit)));
                $this->monitor->registerTruncation();
                $segment->setMetadata('jsonValue', $pathType === 'object'
                    ? ['__class' => $objectClass, '__truncated__' => true, '__items__' => $jsonResult]
                    : ['__truncated__' => true, '__items__' => $jsonResult]
                );
                break;
            }

            $childExpression = $this->extendExpression($expression, $key, $pathType);

            $childSegment = $this->renderValue($val, $depth + 1, $request, $contextVariables, $seen, $childExpression);
            $childJson = $childSegment->metadata()['jsonValue'] ?? null;

            $jsonKey = is_string($key) ? $key : (string) $key;
            $jsonResult[$jsonKey] = $childJson;

            // For object properties, display the sanitized property name with visibility
            $displayKey = $pathType === 'object' && is_string($key)
                ? $this->sanitizePropertyName($key)
                : (is_int($key) ? $key : var_export($key, true));

            $child = new RenderedSegment('array-item', sprintf('[%s]', $displayKey), [
                'key' => $key,
                'expression' => $childExpression,
                'jsonValue' => $childJson,
            ]);
            $child->addChild($childSegment);
            $segment->addChild($child);
            $index++;
        }

        unset($val);
        $this->clearArrayMarker($values);

        $metadata = $segment->metadata();
        if (!array_key_exists('jsonValue', $metadata)) {
            if ($pathType === 'object') {
                $segment->setMetadata('jsonValue', [
                    '__class' => $objectClass,
                    'properties' => $jsonResult,
                ]);
            } else {
                $segment->setMetadata('jsonValue', $jsonResult);
            }
        }

        if ($objectClass !== null) {
            $segment = $segment->withContent('object(' . $objectClass . ')');
        }

        return $segment;
    }

    private function renderString(string $value, ?string $expression): RenderedSegment
    {
        $length = mb_strlen($value);
        $limit = $this->configuration->stringLengthLimit();
        $content = sprintf('string(%d) "%s"', $length, $this->truncateString($value, $limit));
        $segment = new RenderedSegment('string', $content, [
            'expression' => $expression,
            'jsonValue' => $value,
        ]);

        if ($length > $limit) {
            $segment->setMetadata('truncated', true);
            $segment->appendContent(' … truncated');
            $this->monitor->registerTruncation();
        }

        return $segment;
    }

    private function truncateString(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    /**
     * @return array{snapshot: ContextSnapshot, stackTruncated: bool}
     */
    private function prepareContext(DumpRenderRequest $request): array
    {
        $snapshot = $request->context() ?? $this->collector->collect($request);

        $rules = $this->configuration->redactionRules();
        $sanitizedRequest = $this->ensureStringKeys($this->applyRules($snapshot->request(), $rules, RedactionRule::SCOPE_REQUEST));
        $sanitizedEnv = $this->ensureStringKeys($this->applyRules($snapshot->env(), $rules, RedactionRule::SCOPE_ENV));
        $sanitizedVars = $this->ensureStringKeys($this->applyRules($snapshot->variables(), $rules, RedactionRule::SCOPE_PAYLOAD));

        $stack = [];
        foreach ($snapshot->stack() as $frame) {
            $sanitizedArgs = $this->applyRules($frame->args(), $rules, RedactionRule::SCOPE_PAYLOAD);
            $stack[] = new ContextFrame($frame->file(), $frame->line(), $frame->function(), $sanitizedArgs);
        }

        $limit = $this->configuration->stackLimit();
        $stackTruncated = count($stack) > $limit;

        if ($stackTruncated) {
            $stack = array_slice($stack, 0, $limit);
        }

        $sanitizedSnapshot = new ContextSnapshot(
            $snapshot->origin(),
            $stack,
            $sanitizedRequest,
            $sanitizedEnv,
            $sanitizedVars,
        );

        return [
            'snapshot' => $sanitizedSnapshot,
            'stackTruncated' => $stackTruncated,
        ];
    }

    private function renderPerformanceSegment(): RenderedSegment
    {
        $content = sprintf('Rendered in %.2f ms', $this->monitor->durationMs());
        $metadata = [
            'durationMs' => $this->monitor->durationMs(),
            'truncatedSegments' => $this->monitor->truncatedCount(),
        ];

        return new RenderedSegment('performance', $content, $metadata);
    }

    private function renderContextSegment(ContextSnapshot $snapshot, bool $stackTruncated): RenderedSegment
    {
        $origin = $snapshot->origin();
        $lines = [];
        $lines[] = 'Context:';
        $lines[] = sprintf('  Origin: %s:%d', $origin['file'], $origin['line']);

        if ($snapshot->request() !== []) {
            $lines[] = '  Request: ' . $this->stringifyAssoc($snapshot->request());
        }

        if ($snapshot->env() !== []) {
            $lines[] = '  Env: ' . $this->stringifyAssoc($snapshot->env());
        }

        if ($snapshot->variables() !== []) {
            $lines[] = '  Variables: ' . $this->stringifyAssoc($snapshot->variables());
        }

        $stack = $snapshot->stack();
        $lines[] = sprintf('  Stack frames (%d):', count($stack));

        if ($stack === []) {
            $lines[] = '    <empty>';
        } else {
            foreach ($stack as $index => $frame) {
                $lines[] = '    ' . $this->formatStackFrame($frame, $index);
            }
        }

        $segment = new RenderedSegment('context', implode("\n", $lines) . "\n", [
            'truncatedStack' => $stackTruncated,
        ]);

        return $segment;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param list<RedactionRule> $rules
     * @return array<int|string, mixed>
     */
    private function applyRules(array $data, array $rules, string $scope): array
    {
        foreach ($rules as $rule) {
            $data = $rule->applyToArray($data, $scope);
        }

        return $data;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<string, mixed>
     */
    private function ensureStringKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function stringifyAssoc(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = sprintf('%s => %s', (string) $key, $this->stringifyValue($value));
        }

        return implode(', ', $pairs);
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $inner = [];
            $count = 0;
            foreach ($value as $innerKey => $innerValue) {
                $inner[] = sprintf('%s: %s', (string) $innerKey, $this->stringifyValue($innerValue));
                $count++;

                if ($count >= 3) {
                    if (count($value) > $count) {
                        $inner[] = '…';
                    }

                    break;
                }
            }

            return '[' . implode(', ', $inner) . ']';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return 'null';
        }

        return get_debug_type($value);
    }

    private function extendExpression(?string $base, int|string $key, string $pathType): string
    {
        if ($pathType === 'object') {
            $property = $this->sanitizePropertyName((string) $key);

            if ($base === null || $base === '') {
                return '$' . $property;
            }

            return rtrim($base) . '->' . $property;
        }

        $accessor = is_int($key)
            ? sprintf('[%d]', $key)
            : sprintf('[%s]', var_export((string) $key, true));

        if ($base === null || $base === '') {
            return '$value' . $accessor;
        }

        return rtrim($base) . $accessor;
    }

    private function sanitizePropertyName(string $property): string
    {
        if (str_contains($property, "\0")) {
            // Check for private property pattern: \0ClassName\0propertyName
            if (preg_match('/^\x00(.+)\x00(.+)$/', $property, $matches)) {
                $className = $matches[1];
                $propertyName = $matches[2];

                // If it's a protected property, the class name is "*"
                if ($className === '*') {
                    return $propertyName . ':protected';
                }

                // Otherwise it's a private property with the declaring class name
                // Extract just the class name without namespace for brevity
                $lastBackslash = strrpos($className, '\\');
                $shortClassName = $lastBackslash !== false
                    ? substr($className, $lastBackslash + 1)
                    : $className;
                return $propertyName . ':private(' . $shortClassName . ')';
            }

            // Fallback: just remove the null bytes
            $clean = preg_replace('/^\x00.+\x00/', '', $property);
            return $clean === null || $clean === '' ? 'property' : $clean;
        }

        // Public property - add explicit marker
        return $property . ':public';
    }

    private function formatStackFrame(ContextFrame $frame, int $index): string
    {
        $function = $frame->function() ?? '(anonymous)';
        $args = $frame->args();
        $argsString = $args === [] ? '' : sprintf('(%s)', $this->stringifyArguments($args));
        $location = sprintf('%s:%d', $frame->file(), $frame->line());

        return sprintf('#%d %s%s at %s', $index, $function, $argsString, $location);
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private function stringifyArguments(array $args): string
    {
        if ($args === []) {
            return '';
        }

        $parts = [];
        $count = 0;

        foreach ($args as $arg) {
            $parts[] = $this->stringifyValue($arg);
            $count++;

            if ($count >= 3) {
                if (count($args) > $count) {
                    $parts[] = '…';
                }

                break;
            }
        }

        return implode(', ', $parts);
    }

    private function resolveTheme(DumpRenderRequest $request): string
    {
        $themeOption = $request->option('theme');
        $theme = is_string($themeOption) ? $themeOption : $this->configuration->theme();

        if ($theme !== 'auto' && $this->themes->has($theme)) {
            return $theme;
        }

        return $this->themes->defaultForChannel($request->channel())->name();
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function arrayReferenceId(array &$value): string
    {
        if (!isset($value[self::ARRAY_MARKER]) || !is_int($value[self::ARRAY_MARKER])) {
            $value[self::ARRAY_MARKER] = ++$this->arrayMarkerCounter;
        }

        return 'array-' . $value[self::ARRAY_MARKER];
    }

    private function objectReferenceId(object $value): string
    {
        return 'object-' . spl_object_id($value);
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function clearArrayMarker(array &$value): void
    {
        if (isset($value[self::ARRAY_MARKER])) {
            unset($value[self::ARRAY_MARKER]);
        }
    }

    /**
     * Extract all properties from an object using reflection.
     * This includes private, protected, and public properties.
     *
     * @return array<string, mixed>
     */
    private function extractObjectProperties(object $object): array
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($object);

        // Get all declared properties (including inherited ones)
        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();

            // Add visibility prefix to the property name
            if ($property->isPrivate()) {
                $declaringClass = $property->getDeclaringClass()->getName();
                $propertyName = "\0{$declaringClass}\0{$propertyName}";
            } elseif ($property->isProtected()) {
                $propertyName = "\0*\0{$propertyName}";
            }

            // Get the property value safely
            try {
                if ($property->isInitialized($object)) {
                    $properties[$propertyName] = $property->getValue($object);
                } else {
                    // Uninitialized property (PHP 7.4+ typed properties)
                    $properties[$propertyName] = '[uninitialized]';
                }
            } catch (\Throwable $e) {
                // If we can't read the property for any reason, mark it as inaccessible
                $properties[$propertyName] = '[inaccessible]';
            }
        }

        // For objects like stdClass that have dynamic properties,
        // get_object_vars will return them (but not private/protected from other classes)
        // Merge these with reflection-based properties
        $dynamicProperties = get_object_vars($object);
        foreach ($dynamicProperties as $name => $value) {
            // Only add if not already added via reflection (public properties are accessible)
            if (!array_key_exists($name, $properties)) {
                $properties[$name] = $value;
            }
        }

        return $properties;
    }

    private ThemeRegistry $themes;
}
