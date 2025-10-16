<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Renderer;

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;

final class CliRenderer
{
    private const COLOR_RESET = "\033[0m";
    private const COLOR_TYPE = "\033[36m";           // Cyan for types
    private const COLOR_KEY = "\033[33m";            // Yellow for keys
    private const COLOR_STRING = "\033[32m";         // Green for strings
    private const COLOR_NUMBER = "\033[35m";         // Magenta for numbers
    private const COLOR_BOOL = "\033[34m";           // Blue for booleans
    private const COLOR_NULL = "\033[90m";           // Gray for null
    private const COLOR_ARRAY = "\033[36m";          // Cyan for arrays
    private const COLOR_OBJECT = "\033[95m";         // Bright Magenta for objects
    private const COLOR_NOTICE = "\033[35m";         // Magenta for notices
    private const COLOR_EXPRESSION = "\033[90m";     // Bright black for expressions

    private string $indentChar;
    private int $indentSize;
    private bool $showExpressionMeta = false;

    public function __construct(
        private PrettyFormatter $formatter,
        private bool $streamIsTty = true,
    ) {
        $config = $formatter->configuration();
        $this->indentChar = $config->indentStyle() === 'tabs' ? "\t" : ' ';
        $this->indentSize = $config->indentSize();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(DumpRenderRequest $request, array $options = []): string
    {
        $options = $this->sanitizeOptions($options);
        $useColor = $this->shouldUseColor($request, $options);
        $segment = $this->formatter->format($request);
        $this->showExpressionMeta = $this->resolveExpressionMeta($request);

        $output = [];
        foreach ($segment->children() as $child) {
            if ($child->type() === 'context') {
                $contextBlock = $this->renderContextBlock($child, $useColor);
                if ($contextBlock !== '') {
                    $output[] = $contextBlock;
                }

                continue;
            }

            $output[] = $this->renderValueSegment($child, 0, $useColor);
        }

        return implode("\n\n", array_filter($output));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function shouldUseColor(DumpRenderRequest $request, array $options): bool
    {
        if (array_key_exists('color', $options) && !$this->evaluateBool($options['color'], true)) {
            return false;
        }

        if (!$this->evaluateBool($request->option('color', true), true)) {
            return false;
        }

        return $this->streamIsTty;
    }

    private function renderValueSegment(RenderedSegment $segment, int $depth, bool $useColor, bool $isLast = false): string
    {
        return match ($segment->type()) {
            'array' => $this->renderArraySegment($segment, $depth, $useColor, $isLast),
            'array-item' => $this->renderArrayItem($segment, $depth, $useColor, $isLast),
            'string' => $this->renderStringSegment($segment, $depth, $useColor),
            'number' => $this->renderNumberSegment($segment, $depth, $useColor),
            'bool' => $this->renderBoolSegment($segment, $depth, $useColor),
            'null' => $this->renderNullSegment($segment, $depth, $useColor),
            'unknown' => $this->renderUnknownSegment($segment, $depth, $useColor),
            'notice', 'circular' => $this->indent($depth) . $this->colorize($segment->content(), self::COLOR_NOTICE, $useColor),
            'exception' => $this->renderExceptionBlock($segment, $depth),
            'performance' => $this->indent($depth) . $this->colorize($segment->content(), self::COLOR_EXPRESSION, $useColor),
            default => $this->indent($depth) . $segment->content(),
        };
    }

    private function renderLeafValueContent(RenderedSegment $segment, bool $useColor): string
    {
        $content = $segment->content();
        $typeColor = self::COLOR_TYPE;
        $valueColor = match ($segment->type()) {
            'string' => self::COLOR_STRING,
            'number' => self::COLOR_NUMBER,
            'bool' => self::COLOR_BOOL,
            'null' => self::COLOR_NULL,
            default => self::COLOR_TYPE,
        };

        if (preg_match('/^(?<type>[a-z]+\(.*?\))\s+(?<value>.+)$/i', $content, $match)) {
            $typeLabel = $this->colorize($match['type'], $typeColor, $useColor);
            $value = $this->colorize($match['value'], $valueColor, $useColor);
            return $typeLabel . ' ' . $value;
        }

        return $this->colorize($content, $valueColor, $useColor);
    }

    private function renderExpressionSuffix(?string $expression, bool $useColor): string
    {
        if (!$this->showExpressionMeta) {
            return '';
        }

        if ($expression === null || $expression === '') {
            return '';
        }

        if (!$useColor) {
            return sprintf(' (%s)', $expression);
        }

        return ' ' . self::COLOR_EXPRESSION . '(' . $expression . ')' . self::COLOR_RESET;
    }

    private function resolveExpressionMeta(DumpRenderRequest $request): bool
    {
        $configuration = $this->formatter->configuration();
        $enabled = $configuration->showTableVariableMeta();

        return $this->evaluateBool($request->option('showTableVariableMeta'), $enabled);
    }

    private function renderArraySegment(RenderedSegment $segment, int $depth, bool $useColor, bool $isLast): string
    {
        $lines = [];

        // 检查是否为对象
        $isObject = str_contains($segment->content(), 'object(');
        $segmentColor = $isObject ? self::COLOR_OBJECT : self::COLOR_ARRAY;

        // 只显示类型信息，不添加视觉分隔符
        $headerLine = $this->indent($depth) . $this->colorize($segment->content(), $segmentColor, $useColor);
        $headerLine .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);
        $lines[] = $headerLine;

        $children = $segment->children();
        $childCount = count($children);

        foreach ($children as $index => $child) {
            $isChildLast = $index === $childCount - 1;
            $lines[] = $this->renderValueSegment($child, $depth + 1, $useColor, $isChildLast);
        }

        return implode("\n", array_filter($lines));
    }

    private function renderArrayItem(RenderedSegment $segment, int $depth, bool $useColor, bool $isLast): string
    {
        $indent = $this->indent($depth);
        $rawKey = $segment->content();
        $key = $this->colorize($rawKey, self::COLOR_KEY, $useColor);
        $children = $segment->children();
        $firstChild = reset($children);
        $value = $firstChild instanceof RenderedSegment ? $firstChild : null;

        if ($value === null) {
            $symbol = $isLast ? '└── ' : '├── ';
            return $indent . $this->colorize($symbol, self::COLOR_KEY, $useColor) . $key;
        }

        // 添加视觉连接线，保持树形结构连续
        $symbol = $isLast ? '└── ' : '├── ';
        $connector = $this->colorize($symbol, self::COLOR_KEY, $useColor);
        $continuation = $this->colorize($isLast ? '    ' : '│   ', self::COLOR_KEY, $useColor);

        if ($value->children() === []) {
            $requiresMultilineAlignment = $value->type() === 'exception'
                || str_contains($value->content(), "\n");

            if ($requiresMultilineAlignment) {
                $valueOutput = $this->renderValueSegment($value, $depth + 1, $useColor);

                if (str_contains($valueOutput, "\n")) {
                    $valueLines = explode("\n", $valueOutput);
                    $firstLine = (string) array_shift($valueLines);

                    $line = $indent . $connector . $key . ' => ' . $this->stripIndent($firstLine, $depth + 1);
                    $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

                    $keyWidth = $this->stringDisplayWidth($rawKey) + 4;
                    $continuationPadding = $keyWidth > 0 ? str_repeat(' ', $keyWidth) : '';

                    foreach ($valueLines as $childLine) {
                        $line .= "\n" . $indent . $continuation . $continuationPadding . $this->stripIndent($childLine, $depth + 1);
                    }

                    return $line;
                }
            }

            $valueContent = $this->renderLeafValueContent($value, $useColor);
            $line = $indent . $connector . $key . ' => ' . $valueContent;
            $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

            return $line;
        }

        $valueOutput = $this->renderValueSegment($value, $depth + 1, $useColor);
        $valueLines = explode("\n", $valueOutput);
        $firstLine = (string) array_shift($valueLines);

        $lines = [];
        $primaryLine = $indent . $connector . $key . ' => ' . $this->stripIndent($firstLine, $depth + 1);
        $primaryLine .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);
        $lines[] = $primaryLine;

        foreach ($valueLines as $childLine) {
            $lines[] = $indent . $continuation . $this->stripIndent($childLine, $depth + 1);
        }

        return implode("\n", $lines);
    }

    private function stripIndent(string $line, int $depth): string
    {
        $indent = $this->indent($depth);

        if ($indent !== '' && str_starts_with($line, $indent)) {
            return (string) substr($line, strlen($indent));
        }

        return ltrim($line);
    }

    private function stringDisplayWidth(string $text): int
    {
        if (function_exists('mb_strwidth')) {
            return mb_strwidth($text, 'UTF-8');
        }

        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    private function renderStringSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->colorize($segment->content(), self::COLOR_STRING, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderNumberSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->colorize($segment->content(), self::COLOR_NUMBER, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderBoolSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->colorize($segment->content(), self::COLOR_BOOL, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderNullSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->colorize($segment->content(), self::COLOR_NULL, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderUnknownSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->colorize($segment->content(), self::COLOR_TYPE, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderExceptionBlock(RenderedSegment $segment, int $depth): string
    {
        $indent = $this->indent($depth);
        $lines = array_map(
            static fn (string $line): string => $indent . $line,
            explode("\n", $segment->content()),
        );

        return implode("\n", $lines);
    }

    private function renderContextBlock(RenderedSegment $segment, bool $useColor): string
    {
        $lines = [];
        $muted = fn (string $text): string => $this->colorize($text, self::COLOR_EXPRESSION, $useColor);
        $lines[] = $muted('--- Context ---');
        $contentLines = explode("\n", $segment->content());
        $stackHeader = null;
        $stackLines = [];

        foreach ($contentLines as $line) {
            if (str_starts_with($line, '    ')) {
                $stackLines[] = $line;
                continue;
            }

            if ($stackHeader !== null) {
                // $lines[] = $muted($this->formatStackSummary($stackHeader, $stackLines));
                $lines[] = $muted($stackHeader);
                foreach ($stackLines as $stackLine) {
                    $lines[] = $muted($stackLine);
                }
                $stackHeader = null;
                $stackLines = [];
            }

            if (str_starts_with($line, '  Stack frames')) {
                $stackHeader = $line;
                continue;
            }

            $lines[] = $muted($line);
        }

        if ($stackHeader !== null) {
            // $lines[] = $muted($this->formatStackSummary($stackHeader, $stackLines));
            $lines[] = $muted($stackHeader);
            foreach ($stackLines as $stackLine) {
                $lines[] = $muted($stackLine);
            }
        }

        if ($this->evaluateBool($segment->metadata()['truncatedStack'] ?? false, false)) {
            $lines[] = $muted('Stack truncated to configured limit.');
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, string> $stackLines
     */
    private function formatStackSummary(string $header, array $stackLines): string
    {
        if ($stackLines === []) {
            return $header;
        }

        $parts = [];
        foreach ($stackLines as $line) {
            $trimmed = preg_replace('/^#\d+\s+/', '', $line) ?? $line;
            $partsPieces = explode(' at ', $trimmed, 2);
            $function = $partsPieces[0];
            $parts[] = $function === '' ? $trimmed : $function;

            if (count($parts) >= 3) {
                break;
            }
        }

        if (count($stackLines) > 3) {
            $parts[] = '…';
        }

        return rtrim($header) . ' ' . implode(' -> ', $parts);
    }

    private function indent(int $depth): string
    {
        $indentUnit = $this->indentChar === '\t' ? "\t" : str_repeat($this->indentChar, $this->indentSize);
        return str_repeat($indentUnit, $depth);
    }

    private function colorize(string $text, string $color, bool $useColor): string
    {
        if (!$useColor) {
            return $text;
        }

        return $color . $text . self::COLOR_RESET;
    }

    /**
     * @param array<int|string, mixed> $options
     * @return array<string, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function expressionMeta(RenderedSegment $segment): ?string
    {
        $metadata = $segment->metadata();
        $expression = $metadata['expression'] ?? null;

        return is_string($expression) && $expression !== '' ? $expression : null;
    }

    private function evaluateBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return $default;
    }
}
