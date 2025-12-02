<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Renderer;

use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;

/**
 * Renders diff output in Git-like style.
 */
class DiffRenderer
{
    // CLI Colors
    private const COLOR_RESET = "\033[0m";
    private const COLOR_ADDED = "\033[32m";      // Green for additions
    private const COLOR_REMOVED = "\033[31m";    // Red for deletions
    private const COLOR_MODIFIED = "\033[33m";   // Yellow for modifications
    private const COLOR_UNCHANGED = "\033[90m";  // Gray for unchanged
    private const COLOR_KEY = "\033[36m";        // Cyan for keys
    private const COLOR_HEADER = "\033[1;37m";   // Bold white for headers

    /**
     * Render diff for CLI.
     */
    public function renderCli(array $diff, bool $useColor = true, int $depth = 0, string $keyPrefix = ''): string
    {
        $output = [];
        $indent = str_repeat('  ', $depth);

        if (isset($diff['children'])) {
            // Container (array/object)
            foreach ($diff['children'] as $key => $childDiff) {
                $fullKey = $keyPrefix ? "{$keyPrefix}.{$key}" : (string)$key;
                $childOutput = $this->renderDiffItem($key, $childDiff, $depth, $useColor, $fullKey);
                if ($childOutput) {
                    $output[] = $childOutput;
                }
            }
        } else {
            // Leaf node
            $output[] = $this->renderDiffValue($diff, $depth, $useColor, $keyPrefix);
        }

        return implode("\n", $output);
    }

    /**
     * Render a single diff item.
     */
    private function renderDiffItem(string|int $key, array $diff, int $depth, bool $useColor, string $fullKey): string
    {
        $indent = str_repeat('  ', $depth);
        $type = $diff['type'];

        // Format key display
        $keyDisplay = is_numeric($key) ? "[{$key}]" : $key;

        if (isset($diff['children'])) {
            // Has children, recurse
            $header = $indent . $this->colorize($keyDisplay . ':', self::COLOR_KEY, $useColor);
            $childrenOutput = $this->renderCli($diff, $useColor, $depth + 1, $fullKey);

            return $header . "\n" . $childrenOutput;
        }

        // Leaf value
        return $this->renderDiffValue($diff, $depth, $useColor, $keyDisplay);
    }

    /**
     * Render a leaf diff value.
     */
    private function renderDiffValue(array $diff, int $depth, bool $useColor, string $key = ''): string
    {
        $indent = str_repeat('  ', $depth);
        $type = $diff['type'];
        $keyPart = $key ? $this->colorize($key . ': ', self::COLOR_KEY, $useColor) : '';

        switch ($type) {
            case DiffTransformer::DIFF_ADDED:
                $value = $this->formatValue($diff['new']);
                return $indent . $this->colorize('+ ', self::COLOR_ADDED, $useColor) .
                       $keyPart . $this->colorize($value, self::COLOR_ADDED, $useColor);

            case DiffTransformer::DIFF_REMOVED:
                $value = $this->formatValue($diff['old']);
                return $indent . $this->colorize('- ', self::COLOR_REMOVED, $useColor) .
                       $keyPart . $this->colorize($value, self::COLOR_REMOVED, $useColor);

            case DiffTransformer::DIFF_MODIFIED:
                $oldValue = $this->formatValue($diff['old']);
                $newValue = $this->formatValue($diff['new']);
                return $indent . $this->colorize('~ ', self::COLOR_MODIFIED, $useColor) .
                       $keyPart .
                       $this->colorize($oldValue, self::COLOR_REMOVED, $useColor) .
                       $this->colorize(' → ', self::COLOR_MODIFIED, $useColor) .
                       $this->colorize($newValue, self::COLOR_ADDED, $useColor);

            case DiffTransformer::DIFF_UNCHANGED:
                $value = $this->formatValue($diff['new']);
                return $indent . $this->colorize('  ', self::COLOR_UNCHANGED, $useColor) .
                       $keyPart . $this->colorize($value, self::COLOR_UNCHANGED, $useColor);

            default:
                return '';
        }
    }

    /**
     * Format a value for display.
     */
    private function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            $escaped = addslashes($value);
            if (strlen($escaped) > 50) {
                $escaped = substr($escaped, 0, 47) . '...';
            }
            return "\"{$escaped}\"";
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            return '[' . count($value) . ' items]';
        }
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }

    /**
     * Colorize text.
     */
    private function colorize(string $text, string $color, bool $useColor): string
    {
        if (!$useColor) {
            return $text;
        }
        return $color . $text . self::COLOR_RESET;
    }

    /**
     * Render diff for Web (HTML).
     */
    public function renderWeb(array $diff, int $depth = 0, string $keyPrefix = ''): string
    {
        $output = [];
        $indent = str_repeat('  ', $depth);

        if (isset($diff['children'])) {
            // Container
            foreach ($diff['children'] as $key => $childDiff) {
                $fullKey = $keyPrefix ? "{$keyPrefix}.{$key}" : (string)$key;
                $childOutput = $this->renderWebItem($key, $childDiff, $depth, $fullKey);
                if ($childOutput) {
                    $output[] = $childOutput;
                }
            }
        } else {
            // Leaf
            $output[] = $this->renderWebValue($diff, $depth, $keyPrefix);
        }

        return implode("\n", $output);
    }

    /**
     * Render a single diff item for web.
     */
    private function renderWebItem(string|int $key, array $diff, int $depth, string $fullKey): string
    {
        $indent = str_repeat('&nbsp;&nbsp;', $depth);
        $keyDisplay = is_numeric($key) ? "[{$key}]" : htmlspecialchars($key);

        if (isset($diff['children'])) {
            $header = $indent . "<span style='color: #0088cc; font-weight: bold;'>{$keyDisplay}:</span>";
            $children = $this->renderWeb($diff, $depth + 1, $fullKey);
            return $header . "\n" . $children;
        }

        return $this->renderWebValue($diff, $depth, $keyDisplay);
    }

    /**
     * Render a leaf diff value for web.
     */
    private function renderWebValue(array $diff, int $depth, string $key = ''): string
    {
        $indent = str_repeat('&nbsp;&nbsp;', $depth);
        $type = $diff['type'];
        $keyPart = $key ? "<span style='color: #0088cc;'>{$key}: </span>" : '';

        switch ($type) {
            case DiffTransformer::DIFF_ADDED:
                $value = htmlspecialchars($this->formatValue($diff['new']));
                return $indent .
                       "<span style='background: #e6ffed; color: #22863a; padding: 2px 4px; border-radius: 3px;'>" .
                       "+ {$keyPart}{$value}</span>";

            case DiffTransformer::DIFF_REMOVED:
                $value = htmlspecialchars($this->formatValue($diff['old']));
                return $indent .
                       "<span style='background: #ffeef0; color: #cb2431; padding: 2px 4px; border-radius: 3px;'>" .
                       "- {$keyPart}{$value}</span>";

            case DiffTransformer::DIFF_MODIFIED:
                $oldValue = htmlspecialchars($this->formatValue($diff['old']));
                $newValue = htmlspecialchars($this->formatValue($diff['new']));
                return $indent .
                       "<span style='background: #fff8c5; color: #735c0f; padding: 2px 4px; border-radius: 3px;'>" .
                       "~ {$keyPart}" .
                       "<span style='text-decoration: line-through;'>{$oldValue}</span> → " .
                       "<span style='font-weight: bold;'>{$newValue}</span>" .
                       "</span>";

            case DiffTransformer::DIFF_UNCHANGED:
                $value = htmlspecialchars($this->formatValue($diff['new']));
                return $indent .
                       "<span style='color: #999;'>&nbsp;&nbsp;{$keyPart}{$value}</span>";

            default:
                return '';
        }
    }

    /**
     * Generate a summary of changes.
     */
    public function generateSummary(array $diff): array
    {
        $stats = [
            'added' => 0,
            'removed' => 0,
            'modified' => 0,
            'unchanged' => 0,
        ];

        $this->collectStats($diff, $stats);

        return $stats;
    }

    /**
     * Recursively collect statistics.
     */
    private function collectStats(array $diff, array &$stats): void
    {
        if (isset($diff['children'])) {
            foreach ($diff['children'] as $child) {
                $this->collectStats($child, $stats);
            }
        } else {
            $type = $diff['type'];
            switch ($type) {
                case DiffTransformer::DIFF_ADDED:
                    $stats['added']++;
                    break;
                case DiffTransformer::DIFF_REMOVED:
                    $stats['removed']++;
                    break;
                case DiffTransformer::DIFF_MODIFIED:
                    $stats['modified']++;
                    break;
                case DiffTransformer::DIFF_UNCHANGED:
                    $stats['unchanged']++;
                    break;
            }
        }
    }
}
