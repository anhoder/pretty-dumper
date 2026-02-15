<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Renderer;

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;

final class CliRenderer
{
    private const COLOR_RESET = "\033[0m";
    private const COLOR_TYPE = "\033[2;37m";         // Dim light gray for type labels (auxiliary info)
    private const COLOR_KEY = "\033[93m";            // Bright Yellow for property names
    private const COLOR_STRING = "\033[32m";         // Green for strings
    private const COLOR_NUMBER = "\033[35m";         // Magenta for numbers
    private const COLOR_BOOL = "\033[34m";           // Blue for booleans
    private const COLOR_NULL = "\033[2;37m";         // Dim light gray for null
    private const COLOR_ARRAY = "\033[2;37m";        // Dim light gray for array headers (auxiliary)
    private const COLOR_OBJECT = "\033[2;37m";       // Dim light gray for object headers (auxiliary)
    private const COLOR_NOTICE = "\033[35m";         // Magenta for notices
    private const COLOR_EXPRESSION = "\033[2;37m";   // Dim light gray for expressions
    private const COLOR_MODIFIER = "\033[2;37m";     // Dim light gray for access modifiers (private/public/protected)

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
            'json' => $this->renderJsonSegment($segment, $depth, $useColor),
            'sql' => $this->renderSqlSegment($segment, $depth, $useColor),
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
        $key = $this->colorizeKey($rawKey, $useColor);
        $children = $segment->children();
        $firstChild = reset($children);
        $value = $firstChild instanceof RenderedSegment ? $firstChild : null;

        if ($value === null) {
            $symbol = $isLast ? '└── ' : '├── ';
            return $indent . $this->colorize($symbol, self::COLOR_TYPE, $useColor) . $key;
        }

        // 添加视觉连接线，保持树形结构连续
        $symbol = $isLast ? '└── ' : '├── ';
        $connector = $this->colorize($symbol, self::COLOR_TYPE, $useColor);
        $continuation = $this->colorize($isLast ? '    ' : '│   ', self::COLOR_TYPE, $useColor);

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
        $line = $indent . $this->renderLeafValueContent($segment, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderNumberSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->renderLeafValueContent($segment, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderBoolSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->renderLeafValueContent($segment, $useColor);
        $line .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);

        return $line;
    }

    private function renderNullSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $indent = $this->indent($depth);
        $line = $indent . $this->renderLeafValueContent($segment, $useColor);
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

    private function renderJsonSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $lines = [];
        $indent = $this->indent($depth);

        // Render header line: "JSON Document"
        $headerLine = $indent . $this->colorize($segment->content(), self::COLOR_TYPE, $useColor);
        $headerLine .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);
        $lines[] = $headerLine;

        // Render JSON body (formatted JSON content)
        $children = $segment->children();
        foreach ($children as $child) {
            if ($child->type() === 'json-body') {
                $jsonContent = $child->content();
                if ($jsonContent !== '') {
                    // Split formatted JSON into lines and indent each line
                    $jsonLines = explode("\n", $jsonContent);
                    foreach ($jsonLines as $jsonLine) {
                        $highlightedLine = $this->highlightJsonForCli($jsonLine, $useColor);
                        $lines[] = $indent . '  ' . $highlightedLine;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render a SQL segment with syntax highlighting
     */
    private function renderSqlSegment(RenderedSegment $segment, int $depth, bool $useColor): string
    {
        $lines = [];
        $indent = $this->indent($depth);

        // Render header line: "SQL Query"
        $headerLine = $indent . $this->colorize($segment->content(), self::COLOR_TYPE, $useColor);
        $headerLine .= $this->renderExpressionSuffix($this->expressionMeta($segment), $useColor);
        $lines[] = $headerLine;

        // Render SQL body (formatted SQL content)
        $children = $segment->children();
        foreach ($children as $child) {
            if ($child->type() === 'sql-body') {
                $sqlContent = $child->content();
                if ($sqlContent !== '') {
                    // Split formatted SQL into lines and indent each line
                    $sqlLines = explode("\n", $sqlContent);
                    foreach ($sqlLines as $sqlLine) {
                        // Highlight SQL syntax
                        $highlightedLine = $this->highlightSqlForCli($sqlLine, $useColor);
                        $lines[] = $indent . '  ' . $highlightedLine;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Highlight JSON syntax for CLI output using single-pass scanning
     */
    private function highlightJsonForCli(string $jsonLine, bool $useColor): string
    {
        if (!$useColor) {
            return $jsonLine;
        }

        $result = '';
        $len = strlen($jsonLine);
        $i = 0;

        while ($i < $len) {
            $char = $jsonLine[$i];

            // Handle strings (both keys and values)
            if ($char === '"') {
                $stringStart = $i;
                $i++; // Skip opening quote

                // Find closing quote, handling escaped quotes
                while ($i < $len && !($jsonLine[$i] === '"' && $jsonLine[$i - 1] !== '\\')) {
                    $i++;
                }

                if ($i < $len) {
                    $i++; // Include closing quote
                }

                $string = substr($jsonLine, $stringStart, $i - $stringStart);

                // Check if this is a key (followed by colon)
                $nextNonSpace = $i;
                while ($nextNonSpace < $len && $jsonLine[$nextNonSpace] === ' ') {
                    $nextNonSpace++;
                }

                $isKey = $nextNonSpace < $len && $jsonLine[$nextNonSpace] === ':';
                $color = $isKey ? self::COLOR_KEY : self::COLOR_STRING;
                $result .= $this->colorize($string, $color, true);
                continue;
            }

            // Handle numbers
            if (($char >= '0' && $char <= '9') || $char === '-') {
                $numberStart = $i;

                // Match number pattern: -?\d+\.?\d*([eE][+-]?\d+)?
                if ($char === '-') {
                    $i++;
                }

                while ($i < $len && $jsonLine[$i] >= '0' && $jsonLine[$i] <= '9') {
                    $i++;
                }

                if ($i < $len && $jsonLine[$i] === '.') {
                    $i++;
                    while ($i < $len && $jsonLine[$i] >= '0' && $jsonLine[$i] <= '9') {
                        $i++;
                    }
                }

                if ($i < $len && ($jsonLine[$i] === 'e' || $jsonLine[$i] === 'E')) {
                    $i++;
                    if ($i < $len && ($jsonLine[$i] === '+' || $jsonLine[$i] === '-')) {
                        $i++;
                    }
                    while ($i < $len && $jsonLine[$i] >= '0' && $jsonLine[$i] <= '9') {
                        $i++;
                    }
                }

                $number = substr($jsonLine, $numberStart, $i - $numberStart);
                $result .= $this->colorize($number, self::COLOR_NUMBER, true);
                continue;
            }

            // Handle boolean true
            if ($i + 4 <= $len && substr($jsonLine, $i, 4) === 'true') {
                $result .= $this->colorize('true', self::COLOR_BOOL, true);
                $i += 4;
                continue;
            }

            // Handle boolean false
            if ($i + 5 <= $len && substr($jsonLine, $i, 5) === 'false') {
                $result .= $this->colorize('false', self::COLOR_BOOL, true);
                $i += 5;
                continue;
            }

            // Handle null
            if ($i + 4 <= $len && substr($jsonLine, $i, 4) === 'null') {
                $result .= $this->colorize('null', self::COLOR_NULL, true);
                $i += 4;
                continue;
            }

            // Everything else (brackets, braces, colons, commas, spaces)
            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * Highlight SQL syntax for CLI output
     */
    private function highlightSqlForCli(string $sqlLine, bool $useColor): string
    {
        if (!$useColor) {
            return $sqlLine;
        }

        // SQL keywords to highlight
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'CROSS',
            'ON', 'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'BETWEEN', 'LIKE', 'IS', 'NULL',
            'ORDER', 'BY', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'AS',
            'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
            'CREATE', 'TABLE', 'ALTER', 'DROP', 'INDEX', 'VIEW',
            'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN',
            'UNION', 'ALL', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
            'WITH', 'RECURSIVE', 'ASC', 'DESC',
        ];

        $keywordColor = "\033[1;34m";  // Blue
        $stringColor = "\033[0;32m";   // Green
        $numberColor = "\033[0;36m";   // Cyan
        $reset = "\033[0m";

        $result = '';
        $len = strlen($sqlLine);
        $i = 0;

        while ($i < $len) {
            $char = $sqlLine[$i];

            // Handle strings
            if ($char === "'") {
                $stringStart = $i;
                $i++; // Skip opening quote

                // Find closing quote, handling escaped quotes
                while ($i < $len && !($sqlLine[$i] === "'" && $sqlLine[$i - 1] !== '\\')) {
                    $i++;
                }

                if ($i < $len) {
                    $i++; // Include closing quote
                }

                $string = substr($sqlLine, $stringStart, $i - $stringStart);
                $result .= $this->colorize($string, $stringColor, true);
                continue;
            }

            // Handle numbers
            if (($char >= '0' && $char <= '9') || ($char === '-' && $i + 1 < $len && $sqlLine[$i + 1] >= '0' && $sqlLine[$i + 1] <= '9')) {
                $numberStart = $i;

                if ($char === '-') {
                    $i++;
                }

                while ($i < $len && $sqlLine[$i] >= '0' && $sqlLine[$i] <= '9') {
                    $i++;
                }

                if ($i < $len && $sqlLine[$i] === '.') {
                    $i++;
                    while ($i < $len && $sqlLine[$i] >= '0' && $sqlLine[$i] <= '9') {
                        $i++;
                    }
                }

                $number = substr($sqlLine, $numberStart, $i - $numberStart);
                $result .= $this->colorize($number, $numberColor, true);
                continue;
            }

            // Handle keywords (case-insensitive)
            if (ctype_alpha($char)) {
                $wordStart = $i;
                while ($i < $len && ctype_alpha($sqlLine[$i])) {
                    $i++;
                }

                $word = substr($sqlLine, $wordStart, $i - $wordStart);

                // Check if it's a keyword
                $upperWord = strtoupper($word);
                if (in_array($upperWord, $keywords, true)) {
                    $result .= $this->colorize($upperWord, $keywordColor, true);
                } else {
                    $result .= $word;
                }
                continue;
            }

            // Everything else
            $result .= $char;
            $i++;
        }

        return $result;
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
     * Colorize property key with separate colors for name and modifier.
     * Examples:
     *   [total:private(QueryComplexityResult)] => name=total, modifier=:private(QueryComplexityResult)
     *   ['select_field_count'] => name='select_field_count', no modifier
     */
    private function colorizeKey(string $rawKey, bool $useColor): string
    {
        if (!$useColor) {
            return $rawKey;
        }

        // Match patterns like [propertyName:modifier(ClassName)] or ['arrayKey']
        // Pattern: [name:modifier(class)] or [name] or ['key'] or ["key"]
        if (preg_match('/^\[([^\]:\'"]+)(:[^\]]+)?\]$/', $rawKey, $match)) {
            // Object property with optional modifier: [total:private(QueryComplexityResult)]
            $name = $match[1];
            $modifier = $match[2] ?? '';

            $coloredName = self::COLOR_KEY . '[' . $name . self::COLOR_RESET;
            if ($modifier !== '') {
                $coloredName .= self::COLOR_MODIFIER . $modifier . self::COLOR_RESET;
            }
            $coloredName .= self::COLOR_KEY . ']' . self::COLOR_RESET;

            return $coloredName;
        }

        if (preg_match('/^\[([\'"])(.+)\1\]$/', $rawKey, $match)) {
            // Array string key: ['key'] or ["key"]
            $quote = $match[1];
            $keyName = $match[2];

            return self::COLOR_KEY . '[' . $quote . $keyName . $quote . ']' . self::COLOR_RESET;
        }

        if (preg_match('/^\[(\d+)\]$/', $rawKey, $match)) {
            // Array numeric key: [0], [1], etc.
            return self::COLOR_MODIFIER . $rawKey . self::COLOR_RESET;
        }

        // Fallback: colorize entire key
        return self::COLOR_KEY . $rawKey . self::COLOR_RESET;
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
