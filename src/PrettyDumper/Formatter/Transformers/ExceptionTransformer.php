<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter\Transformers;

use Anhoder\PrettyDumper\Context\RedactionRule;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;
use Throwable;

final class ExceptionTransformer
{
    /**
     * @param list<RedactionRule> $rules
     * @param array<string, mixed> $variables
     */
    public function transform(Throwable $throwable, FormatterConfiguration $configuration, array $rules, array $variables = []): RenderedSegment
    {
        $chain = $this->buildChain($throwable);
        $content = [];
        $stackFrames = [];

        foreach ($chain as $index => $exception) {
            $content[] = $this->formatExceptionBlock($exception, $index === 0, $configuration, $rules, $variables);
            // 获取所有堆栈帧信息（不只是格式化的文本）
            $stackFrames = array_merge($stackFrames, $this->extractStackFrames($exception));
        }

        return new RenderedSegment('exception', implode("\n\n", $content), [
            'exceptions' => count($chain),
            'stackFrames' => $stackFrames, // 提供完整的堆栈帧数据
            'hasFullStack' => true, // 标记包含完整堆栈
        ]);
    }

    /**
     * @return list<Throwable>
     */
    private function buildChain(Throwable $throwable): array
    {
        $chain = [];
        $current = $throwable;

        while ($current !== null) {
            $chain[] = $current;
            $current = $current->getPrevious();
        }

        return $chain;
    }

    /**
     * @param list<RedactionRule> $rules
     * @param array<string, mixed> $variables
     */
    private function formatExceptionBlock(Throwable $exception, bool $isPrimary, FormatterConfiguration $configuration, array $rules, array $variables): string
    {
        $lines = [];
        $header = $isPrimary ? 'Exception' : 'Caused by';
        $lines[] = sprintf('%s: %s (code: %d)', $header, $exception::class, $exception->getCode());

        $message = $this->maskSensitiveFragments($exception->getMessage(), $rules);
        // $message = $this->truncateMessage($message, $configuration->messageLimit());
        $lines[] = sprintf('Message: %s', $message);

        $file = $this->normalizePath($exception->getFile());
        $lines[] = sprintf('Location: %s:%d', $file, $exception->getLine());
        $lines[] = 'Trace:';
        $traceLines = $this->formatTrace($exception);
        foreach ($traceLines as $traceLine) {
            $lines[] = '  ' . $traceLine;
        }

        if ($isPrimary && $configuration->option('includeVariableSnapshots', false) && $variables !== []) {
            $lines[] = 'Variables:';
            foreach ($variables as $name => $value) {
                $lines[] = sprintf('  %s => %s', (string) $name, $this->stringify($value));
            }
        }

        return implode("\n", $lines);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        // $parts = explode('/', $normalized);

        // if (count($parts) <= 4) {
        //     return $normalized;
        // }

        // $tail = array_slice($parts, -3);

        // return '…/' . implode('/', $tail);

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function formatTrace(Throwable $exception): array
    {
        $lines = [];
        /** @var list<array<string, mixed>> $trace */
        $trace = $exception->getTrace();

        foreach ($trace as $index => $frame) {

            $file = isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : 'unknown';
            $line = isset($frame['line']) && is_numeric($frame['line']) ? (int) $frame['line'] : 0;
            $function = isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : 'unknown';
            $args = isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : [];
            $arguments = $this->summarizeArgs($args);

            $lines[] = sprintf('  #%d %s:%d %s(%s)', $index, $this->normalizePath($file), $line, $function, $arguments);
        }

        if ($lines === []) {
            $lines[] = '  #0 <no trace available>';
        }

        return $lines;
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private function summarizeArgs(array $args): string
    {
        if ($args === []) {
            return '';
        }

        $summary = [];
        foreach ($args as $value) {
            $summary[] = $this->stringify($value);
        }

        return implode(', ', $summary);
    }

    private function truncateMessage(string $message, int $limit): string
    {
        // if (mb_strlen($message) <= $limit) {
        //     return $message;
        // }

        // return mb_substr($message, 0, $limit) . '… truncated (show more)';

        return $message;
    }

    /**
     * @param list<RedactionRule> $rules
     */
    private function maskSensitiveFragments(string $message, array $rules): string
    {
        $keywords = [];

        foreach ($rules as $rule) {
            $keyword = $this->extractKeyword($rule->pattern());
            if ($keyword !== null) {
                $keywords[] = $keyword;
            }
        }

        if ($keywords === []) {
            return $message;
        }

        $pattern = sprintf('/(%s)=[^\s]+/i', implode('|', array_map(static fn (string $kw): string => preg_quote($kw, '/'), $keywords)));

        $result = preg_replace($pattern, '$1=***', $message);

        return $result ?? $message;
    }

    private function extractKeyword(string $pattern): ?string
    {
        if ($pattern === '') {
            return null;
        }

        if ($pattern[0] === '/' && preg_match('/^\/(.+)\/[a-z]*$/i', $pattern, $matches) === 1) {
            return $matches[1];
        }

        return $pattern;
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];
            foreach ($value as $key => $inner) {
                $parts[] = sprintf('%s: %s', (string) $key, $this->stringify($inner));
            }

            return '[' . implode(', ', $parts) . ']';
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof Throwable) {
            return $value::class;
        }

        return get_debug_type($value);
    }

    /**
     * 提取完整的堆栈帧信息，不只是格式化的文本
     * @return list<array{file: string, line: int, function: string, class?: string, type?: string, args: array<int|string, mixed>}>
     */
    private function extractStackFrames(Throwable $exception): array
    {
        $frames = [];
        /** @var list<array<string, mixed>> $trace */
        $trace = $exception->getTrace();

        foreach ($trace as $frame) {

            $file = isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : 'unknown';
            $line = isset($frame['line']) && is_numeric($frame['line']) ? (int) $frame['line'] : 0;
            $function = isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : 'unknown';
            $class = isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : null;
            $type = isset($frame['type']) && is_string($frame['type']) ? $frame['type'] : null;
            $args = isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : [];

            $entry = [
                'file' => $this->normalizePath($file),
                'line' => $line,
                'function' => $function,
                'args' => $args,
            ];

            if ($class !== null) {
                $entry['class'] = $class;
            }

            if ($type !== null) {
                $entry['type'] = $type;
            }

            $frames[] = $entry;
        }

        return $frames;
    }
}
