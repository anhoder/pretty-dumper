<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter\Transformers;

use Anhoder\PrettyDumper\Formatter\RenderedSegment;

/**
 * SqlTransformer detects and formats SQL queries.
 */
class SqlTransformer
{
    /** SQL keywords that should be uppercase and highlighted */
    private const KEYWORDS = [
        'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'CROSS',
        'ON', 'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'BETWEEN', 'LIKE', 'IS', 'NULL',
        'ORDER', 'BY', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'AS',
        'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
        'CREATE', 'TABLE', 'ALTER', 'DROP', 'INDEX', 'VIEW',
        'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN',
        'UNION', 'ALL', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
        'WITH', 'RECURSIVE', 'ASC', 'DESC',
    ];

    /** SQL functions */
    private const FUNCTIONS = [
        'COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'CONCAT', 'SUBSTRING',
        'UPPER', 'LOWER', 'TRIM', 'LENGTH', 'NOW', 'DATE', 'TIME',
        'COALESCE', 'IFNULL', 'NULLIF', 'CAST', 'CONVERT',
    ];

    /**
     * Detect if a string is likely a SQL query.
     */
    public function isSql(string $value): bool
    {
        $value = trim($value);

        // Must start with a SQL keyword
        $startsWithKeyword = preg_match('/^(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|WITH)\s+/i', $value);

        if (!$startsWithKeyword) {
            return false;
        }

        // Should contain common SQL patterns
        $hasCommonPatterns = preg_match('/(FROM|WHERE|SET|VALUES|INTO|TABLE)\s+/i', $value);

        return (bool)$hasCommonPatterns;
    }

    /**
     * Format a SQL query with proper indentation and syntax highlighting.
     */
    public function format(string $sql, array $bindings = []): string
    {
        // Replace bindings if provided
        if (!empty($bindings)) {
            $sql = $this->replaceBindings($sql, $bindings);
        }

        // Normalize whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = trim($sql);

        // Add line breaks before major keywords
        $majorKeywords = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN',
                         'INNER JOIN', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'UNION',
                         'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE FROM'];

        foreach ($majorKeywords as $keyword) {
            $sql = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/i', "\n" . strtoupper($keyword), $sql);
        }

        // Add line breaks after commas in SELECT
        $sql = preg_replace('/,(?=[^()]*(?:\(|$))/', ",\n    ", $sql);

        // Clean up extra whitespace
        $lines = explode("\n", $sql);
        $formatted = [];
        $indent = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Adjust indent
            if (preg_match('/^(FROM|WHERE|ORDER BY|GROUP BY|HAVING|LIMIT|UNION)/', $line)) {
                $indent = 0;
            } elseif (preg_match('/^(JOIN|LEFT JOIN|RIGHT JOIN|INNER JOIN|AND|OR)/', $line)) {
                $indent = 1;
            } elseif (preg_match('/^(SELECT|INSERT|UPDATE|DELETE)/', $line)) {
                $indent = 0;
            }

            $formatted[] = str_repeat('  ', $indent) . $line;
        }

        return implode("\n", $formatted);
    }

    /**
     * Replace parameter bindings in SQL.
     *
     * @param string $sql SQL query with ? or :name placeholders
     * @param array<mixed> $bindings Parameter values
     */
    private function replaceBindings(string $sql, array $bindings): string
    {
        // Handle named parameters (:name)
        if ($this->hasNamedParameters($sql)) {
            foreach ($bindings as $key => $value) {
                $placeholder = is_numeric($key) ? '?' : ':' . ltrim((string)$key, ':');
                $sql = str_replace($placeholder, $this->formatBindingValue($value), $sql);
            }
            return $sql;
        }

        // Handle positional parameters (?)
        $offset = 0;
        foreach ($bindings as $value) {
            $pos = strpos($sql, '?', $offset);
            if ($pos === false) {
                break;
            }

            $sql = substr_replace($sql, $this->formatBindingValue($value), $pos, 1);
            $offset = $pos + strlen($this->formatBindingValue($value));
        }

        return $sql;
    }

    /**
     * Check if SQL has named parameters.
     */
    private function hasNamedParameters(string $sql): bool
    {
        return (bool)preg_match('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql);
    }

    /**
     * Format a binding value for display in SQL.
     */
    private function formatBindingValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_array($value)) {
            return '(' . implode(', ', array_map([$this, 'formatBindingValue'], $value)) . ')';
        }

        return "'" . addslashes((string)$value) . "'";
    }

    /**
     * Create a RenderedSegment for formatted SQL.
     */
    public function createSqlSegment(string $sql, array $bindings = [], ?array $explain = null): RenderedSegment
    {
        $formatted = $this->format($sql, $bindings);

        $metadata = [
            'type' => 'sql',
            'original' => $sql,
            'bindings' => $bindings,
        ];

        if ($explain !== null) {
            $metadata['explain'] = $explain;
        }

        $segment = new RenderedSegment('sql', $formatted, $metadata);

        // Add explain plan as child if available
        if ($explain !== null) {
            $explainSegment = new RenderedSegment(
                'sql-explain',
                $this->formatExplain($explain),
                ['data' => $explain]
            );
            $segment->addChild($explainSegment);
        }

        return $segment;
    }

    /**
     * Format EXPLAIN output.
     */
    private function formatExplain(array $explain): string
    {
        if (empty($explain)) {
            return 'No EXPLAIN data available';
        }

        $output = "EXPLAIN:\n";

        foreach ($explain as $row) {
            if (is_array($row)) {
                $output .= "  • " . json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $output .= "  • " . $row . "\n";
            }
        }

        return $output;
    }

    /**
     * Highlight SQL syntax for CLI output.
     */
    public function highlightForCli(string $sql): string
    {
        // Color codes for CLI
        $keywordColor = "\033[1;34m";  // Blue
        $stringColor = "\033[0;32m";   // Green
        $numberColor = "\033[0;36m";   // Cyan
        $reset = "\033[0m";

        // Highlight keywords
        foreach (self::KEYWORDS as $keyword) {
            $sql = preg_replace(
                '/\b' . preg_quote($keyword, '/') . '\b/i',
                $keywordColor . strtoupper($keyword) . $reset,
                $sql
            );
        }

        // Highlight strings
        $sql = preg_replace(
            "/'([^']*?)'/",
            $stringColor . "'$1'" . $reset,
            $sql
        );

        // Highlight numbers
        $sql = preg_replace(
            '/\b(\d+)\b/',
            $numberColor . '$1' . $reset,
            $sql
        );

        return $sql;
    }

    /**
     * Highlight SQL syntax for HTML output.
     */
    public function highlightForWeb(string $sql): string
    {
        // Escape HTML
        $sql = htmlspecialchars($sql);

        // Highlight keywords
        foreach (self::KEYWORDS as $keyword) {
            $sql = preg_replace(
                '/\b' . preg_quote($keyword, '/') . '\b/i',
                '<span style="color: #0066cc; font-weight: bold;">' . strtoupper($keyword) . '</span>',
                $sql
            );
        }

        // Highlight strings
        $sql = preg_replace(
            "/'([^']*?)'/",
            '<span style="color: #00aa00;">\'$1\'</span>',
            $sql
        );

        // Highlight numbers
        $sql = preg_replace(
            '/\b(\d+)\b/',
            '<span style="color: #aa6600;">$1</span>',
            $sql
        );

        // Convert line breaks to <br>
        $sql = nl2br($sql);

        return $sql;
    }

    /**
     * Try to execute EXPLAIN on a query (if PDO connection is available).
     *
     * @param string $sql SQL query
     * @param \PDO|null $pdo Optional PDO connection
     * @return array|null EXPLAIN results
     */
    public function getExplain(string $sql, ?\PDO $pdo = null): ?array
    {
        if ($pdo === null) {
            return null;
        }

        try {
            // Only explain SELECT queries
            if (!preg_match('/^\s*SELECT/i', $sql)) {
                return null;
            }

            $stmt = $pdo->query('EXPLAIN ' . $sql);
            return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
