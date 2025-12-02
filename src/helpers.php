<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;
use Anhoder\PrettyDumper\Formatter\Transformers\SqlTransformer;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Anhoder\PrettyDumper\Renderer\DiffRenderer;
use Anhoder\PrettyDumper\Renderer\WebRenderer;
use Anhoder\PrettyDumper\Storage\DumpHistoryStorage;
use Anhoder\PrettyDumper\Support\ThemeRegistry;

if (!function_exists('__pretty_dumper_detect_channel')) {
    /**
     * Detect whether the current context is CLI or Web.
     *
     * This method checks for HTTP-related context rather than relying solely on PHP_SAPI,
     * which allows it to correctly identify web contexts in Workerman, Swoole, RoadRunner, etc.
     * where PHP_SAPI is 'cli' but the application is serving HTTP requests.
     */
    function __pretty_dumper_detect_channel(): string
    {
        // Check for HTTP request context indicators
        // These are present in web environments including Workerman, Swoole, RoadRunner

        // 1. Check for CGI/FPM SAPI (definitely web)
        if (in_array(PHP_SAPI, ['cgi', 'cgi-fcgi', 'fpm-fcgi'], true)) {
            return 'web';
        }

        // 2. Check for HTTP-related superglobals (most reliable for Workerman/Swoole)
        if (!empty($_SERVER['REQUEST_METHOD']) ||
            !empty($_SERVER['HTTP_HOST']) ||
            !empty($_SERVER['REQUEST_URI'])) {
            return 'web';
        }

        // 3. Check for output buffering with web-related content
        if (ob_get_level() > 0) {
            $content = ob_get_contents();
            if ($content !== false && (
                stripos($content, '<html') !== false ||
                stripos($content, '<!DOCTYPE') !== false ||
                stripos($content, 'Content-Type: text/html') !== false
            )) {
                return 'web';
            }
        }

        // 4. Fallback to CLI for true CLI environments
        return 'cli';
    }
}

if (!function_exists('__pretty_dumper_is_tty')) {
    function __pretty_dumper_is_tty(): bool
    {
        if (!defined('STDOUT')) {
            return false;
        }

        if (!function_exists('stream_isatty')) {
            return \in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
        }

        try {
            return stream_isatty(STDOUT);
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('pretty_dump')) {
    /**
     * Render a variable using the appropriate renderer based on the runtime channel.
     *
     * @param mixed $value Value to dump.
     * @param array<string, mixed> $options Formatter configuration / request options.
     * @param bool $output When true (default) the dump is written directly, otherwise returned.
     * @return string|null
     */
    function pretty_dump(mixed $value, array $options = [], bool $output = true): ?string
    {
        $channel = $options['channel'] ?? __pretty_dumper_detect_channel();
        if (!\in_array($channel, ['cli', 'web'], true)) {
            $channel = __pretty_dumper_detect_channel();
        }

        unset($options['channel']);

        $configuration = new FormatterConfiguration($options, $channel);
        $formatter     = PrettyFormatter::forChannel($channel, $configuration);
        $request       = new DumpRenderRequest($value, $channel, $options);

        if ($channel === 'cli') {
            $renderer = new CliRenderer($formatter, __pretty_dumper_is_tty());

            $renderOptions = [];
            if (array_key_exists('color', $options)) {
                $renderOptions['color'] = (bool)$options['color'];
            }

            $result = $renderer->render($request, $renderOptions);

            if (!$output) {
                return $result;
            }

            if (defined('STDOUT')) {
                fwrite(STDOUT, $result . PHP_EOL);
            } else {
                echo $result, PHP_EOL;
            }

            return null;
        }

        /** @var ThemeRegistry|null $themeRegistry */
        static $themeRegistry = null;
        if ($themeRegistry === null) {
            $themeRegistry = ThemeRegistry::withDefaults();
        }

        $renderer = new WebRenderer($formatter, $themeRegistry);
        $result   = $renderer->render($request);

        if (!$output) {
            return $result;
        }

        echo $result;

        return null;
    }
}

if (!function_exists('pd')) {
    /**
     * Alias for pretty_dump.
     *
     * @param mixed $value Value to dump.
     * @param array<string, mixed> $options Formatter configuration / request options.
     * @param bool $output When true (default) the dump is written directly, otherwise returned.
     * @return string|null
     */
    function pd(mixed $value, array $options = [], bool $output = true): ?string
    {
        return pretty_dump($value, $options, $output);
    }
}

if (!function_exists('__pretty_dumper_json_stringify')) {
    function __pretty_dumper_json_stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            return '"[unserializable]"';
        }

        return $json;
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        if ($values === []) {
            $values = [null];
        }

        $len = count($values);
        foreach ($values as $index => $value) {
            $options = $index === $len - 1 ? [] : ['showContext' => false];

            pretty_dump($value, $options);
        }
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): void
    {
        dump(...$values);
        exit(0);
    }
}

if (!function_exists('pdd')) {
    /**
     * @param array<string, mixed> $options
     */
    function pdd(mixed $value, array $options = [], bool $output = true): void
    {
        pd($value, $options, $output);
        exit(0);
    }
}


if (!function_exists('dumpj')) {
    /**
     * @param array<int, mixed> $values
     */
    function dumpj(mixed ...$values): void
    {
        if ($values === []) {
            $values = [null];
        }

        $len = count($values);
        foreach ($values as $index => $value) {
            $options = ['autoDetectJson' => true];

            if ($index !== $len - 1) {
                $options['showContext'] = false;
            }

            pretty_dump(__pretty_dumper_json_stringify($value), $options);
        }
    }
}

if (!function_exists('pdj')) {
    /**
     * @param array<string, mixed> $options
     */
    function pdj(mixed $value, array $options = [], bool $output = true): void
    {
        $options['autoDetectJson'] = true;
        pretty_dump(__pretty_dumper_json_stringify($value), $options, $output);
    }
}

if (!function_exists('ddj')) {
    /**
     * @param array<int, mixed> $values
     */
    function ddj(mixed ...$values): void
    {
        dumpj(...$values);
        exit(0);
    }
}

if (!function_exists('pddj')) {
    /**
     * @param array<string, mixed> $options
     */
    function pddj(mixed $value, array $options = [], bool $output = true): void
    {
        pdj($value, $options, $output);
        exit(0);
    }
}

// ============================================================================
// Diff Dumper Functions
// ============================================================================

if (!function_exists('pd_diff')) {
    /**
     * Compare two values and display the diff in Git-like style.
     *
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_diff(mixed $oldValue, mixed $newValue, array $options = [], bool $output = true): ?string
    {
        // Auto-detect JSON strings
        if (is_string($oldValue) && is_string($newValue)) {
            $oldJson = json_decode($oldValue, true);
            $newJson = json_decode($newValue, true);

            if (json_last_error() === JSON_ERROR_NONE && $oldJson !== null && $newJson !== null) {
                $oldValue = $oldJson;
                $newValue = $newJson;
            }
        }

        $transformer = new DiffTransformer();
        $diff = $transformer->diff($oldValue, $newValue);
        $diffRenderer = new DiffRenderer();
        $stats = $diffRenderer->generateSummary($diff);

        $channel = $options['channel'] ?? __pretty_dumper_detect_channel();
        $useColor = !isset($options['color']) || $options['color'] !== false;

        if ($channel === 'cli') {
            $result = '';

            // Header
            if ($useColor) {
                $result .= "\n\033[1;37m" . str_repeat('=', 60) . "\033[0m\n";
                $result .= "\033[1;37mDiff Summary:\033[0m ";
                $result .= "\033[32m+{$stats['added']}\033[0m ";
                $result .= "\033[31m-{$stats['removed']}\033[0m ";
                $result .= "\033[33m~{$stats['modified']}\033[0m ";
                $result .= "\033[90m={$stats['unchanged']}\033[0m\n";
                $result .= "\033[1;37m" . str_repeat('=', 60) . "\033[0m\n";
            } else {
                $result .= "\n" . str_repeat('=', 60) . "\n";
                $result .= "Diff Summary: ";
                $result .= "+{$stats['added']} ";
                $result .= "-{$stats['removed']} ";
                $result .= "~{$stats['modified']} ";
                $result .= "={$stats['unchanged']}\n";
                $result .= str_repeat('=', 60) . "\n";
            }

            // Diff content
            $result .= $diffRenderer->renderCli($diff, $useColor && __pretty_dumper_is_tty());
            $result .= "\n" . ($useColor ? "\033[1;37m" . str_repeat('=', 60) . "\033[0m" : str_repeat('=', 60)) . "\n";

            if ($output) {
                echo $result;
                return null;
            }
            return $result;
        } else {
            // Web output
            $result = "<div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 16px; margin: 8px 0; font-family: monospace;'>";

            // Header
            $result .= "<div style='font-weight: bold; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #dee2e6;'>";
            $result .= "Diff Summary: ";
            $result .= "<span style='color: #22863a; background: #e6ffed; padding: 2px 6px; border-radius: 3px; margin: 0 4px;'>+{$stats['added']}</span>";
            $result .= "<span style='color: #cb2431; background: #ffeef0; padding: 2px 6px; border-radius: 3px; margin: 0 4px;'>-{$stats['removed']}</span>";
            $result .= "<span style='color: #735c0f; background: #fff8c5; padding: 2px 6px; border-radius: 3px; margin: 0 4px;'>~{$stats['modified']}</span>";
            $result .= "<span style='color: #999; background: #f6f8fa; padding: 2px 6px; border-radius: 3px; margin: 0 4px;'>={$stats['unchanged']}</span>";
            $result .= "</div>";

            // Diff content
            $result .= "<pre style='margin: 0; overflow-x: auto; line-height: 1.6;'>";
            $result .= $diffRenderer->renderWeb($diff);
            $result .= "</pre>";

            $result .= "</div>";

            if ($output) {
                echo $result;
                return null;
            }
            return $result;
        }
    }
}

if (!function_exists('pd_auto_diff')) {
    /**
     * Automatically compare with the last dumped value at this location.
     *
     * @param mixed $value Value to dump and compare
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_auto_diff(mixed $value, array $options = [], bool $output = true): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $location = DumpHistoryStorage::generateLocation($trace);

        $lastValue = DumpHistoryStorage::getLast($location);

        if ($lastValue !== null) {
            // We have history, show diff
            $result = pd_diff($lastValue, $value, $options, $output);
        } else {
            // No history, just dump normally
            $result = pretty_dump($value, $options, $output);
        }

        // Store current value for next comparison
        DumpHistoryStorage::store($location, $value);

        return $result;
    }
}

if (!function_exists('pdd_diff')) {
    /**
     * Diff and die.
     *
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @param array<string, mixed> $options Formatter configuration options
     */
    function pdd_diff(mixed $oldValue, mixed $newValue, array $options = []): void
    {
        pd_diff($oldValue, $newValue, $options);
        exit(0);
    }
}

if (!function_exists('pd_clear_history')) {
    /**
     * Clear dump history (useful for testing).
     */
    function pd_clear_history(?string $location = null): void
    {
        if ($location === null) {
            DumpHistoryStorage::clearAll();
        } else {
            DumpHistoryStorage::clear($location);
        }
    }
}

// ============================================================================
// Conditional Dump Functions
// ============================================================================

if (!function_exists('pd_when')) {
    /**
     * Dump only when condition is met.
     *
     * @param mixed $value Value to potentially dump
     * @param callable|bool $condition Condition callback or boolean
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_when(mixed $value, callable|bool $condition, array $options = [], bool $output = true): ?string
    {
        $shouldDump = is_callable($condition) ? $condition($value) : $condition;

        if (!$shouldDump) {
            return null;
        }

        return pretty_dump($value, $options, $output);
    }
}

if (!function_exists('pdd_when')) {
    /**
     * Dump and die only when condition is met.
     *
     * @param mixed $value Value to potentially dump
     * @param callable|bool $condition Condition callback or boolean
     * @param array<string, mixed> $options Formatter configuration options
     */
    function pdd_when(mixed $value, callable|bool $condition, array $options = []): void
    {
        $shouldDump = is_callable($condition) ? $condition($value) : $condition;

        if (!$shouldDump) {
            return;
        }

        pretty_dump($value, $options);
        exit(0);
    }
}

if (!function_exists('pd_assert')) {
    /**
     * Assert a condition and dump with warning if it fails.
     *
     * @param mixed $value Value to dump
     * @param callable|bool $assertion Assertion callback or boolean
     * @param string $message Optional message to display on failure
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_assert(mixed $value, callable|bool $assertion, string $message = '', array $options = [], bool $output = true): ?string
    {
        $passed = is_callable($assertion) ? $assertion($value) : $assertion;

        $options['_isAssertion'] = true;
        $options['_assertionPassed'] = $passed;
        $options['_assertionMessage'] = $message;

        if (!$passed && $message) {
            $channel = $options['channel'] ?? __pretty_dumper_detect_channel();

            if ($channel === 'cli') {
                $warning = "\n\033[1;31m⚠ Assertion Failed: {$message}\033[0m\n";
            } else {
                $warning = "<div style='background: #fee; border-left: 4px solid #f00; padding: 12px; margin: 8px 0;'>" .
                          "<strong style='color: #c00;'>⚠ Assertion Failed:</strong> " .
                          htmlspecialchars($message) . "</div>";
            }

            if ($output) {
                echo $warning;
            }
        }

        return pretty_dump($value, $options, $output);
    }
}

if (!function_exists('pdd_assert')) {
    /**
     * Assert and die.
     *
     * @param mixed $value Value to dump
     * @param callable|bool $assertion Assertion callback or boolean
     * @param string $message Optional message to display on failure
     * @param array<string, mixed> $options Formatter configuration options
     */
    function pdd_assert(mixed $value, callable|bool $assertion, string $message = '', array $options = []): void
    {
        pd_assert($value, $assertion, $message, $options);
        exit(0);
    }
}

// ============================================================================
// SQL Dump Functions
// ============================================================================

if (!function_exists('pd_sql')) {
    /**
     * Dump SQL query with syntax highlighting and optional EXPLAIN.
     *
     * @param string $sql SQL query
     * @param array<mixed> $bindings Optional parameter bindings
     * @param \PDO|null $pdo Optional PDO connection for EXPLAIN
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_sql(string $sql, array $bindings = [], ?\PDO $pdo = null, array $options = [], bool $output = true): ?string
    {
        $transformer = new SqlTransformer();

        // Auto-detect if it's SQL
        $isSql = $transformer->isSql($sql);

        if (!$isSql) {
            // Not SQL, dump as regular string
            return pretty_dump($sql, $options, $output);
        }

        // Format SQL
        $formatted = $transformer->format($sql, $bindings);

        // Try to get EXPLAIN if PDO is provided
        $explain = null;
        if ($pdo !== null) {
            $explain = $transformer->getExplain($sql, $pdo);
        }

        $channel = $options['channel'] ?? __pretty_dumper_detect_channel();

        // Apply syntax highlighting
        if ($channel === 'cli') {
            $highlighted = $transformer->highlightForCli($formatted);
        } else {
            $highlighted = $transformer->highlightForWeb($formatted);
        }

        $options['_isSql'] = true;
        $options['_sqlOriginal'] = $sql;
        $options['_sqlFormatted'] = $formatted;
        $options['_sqlBindings'] = $bindings;
        $options['_sqlExplain'] = $explain;

        if ($channel === 'cli') {
            if ($output) {
                echo "\n" . str_repeat('=', 60) . "\n";
                echo "SQL Query:\n";
                echo str_repeat('=', 60) . "\n";
                echo $highlighted . "\n";

                if (!empty($bindings)) {
                    echo "\n" . str_repeat('-', 60) . "\n";
                    echo "Bindings:\n";
                    echo str_repeat('-', 60) . "\n";
                    pretty_dump($bindings);
                }

                if ($explain !== null && !empty($explain)) {
                    echo "\n" . str_repeat('-', 60) . "\n";
                    echo "EXPLAIN:\n";
                    echo str_repeat('-', 60) . "\n";
                    pretty_dump($explain);
                }

                echo str_repeat('=', 60) . "\n\n";
                return null;
            } else {
                return $highlighted;
            }
        } else {
            // Web output
            $html = "<div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 16px; margin: 8px 0; font-family: monospace;'>";
            $html .= "<div style='font-weight: bold; margin-bottom: 8px; color: #495057;'>SQL Query:</div>";
            $html .= "<pre style='margin: 0; overflow-x: auto;'>" . $highlighted . "</pre>";

            if (!empty($bindings)) {
                $html .= "<div style='margin-top: 12px; padding-top: 12px; border-top: 1px solid #dee2e6;'>";
                $html .= "<div style='font-weight: bold; margin-bottom: 8px; color: #495057;'>Bindings:</div>";
                $html .= pretty_dump($bindings, array_merge($options, ['showContext' => false]), false);
                $html .= "</div>";
            }

            if ($explain !== null && !empty($explain)) {
                $html .= "<div style='margin-top: 12px; padding-top: 12px; border-top: 1px solid #dee2e6;'>";
                $html .= "<div style='font-weight: bold; margin-bottom: 8px; color: #495057;'>EXPLAIN:</div>";
                $html .= pretty_dump($explain, array_merge($options, ['showContext' => false]), false);
                $html .= "</div>";
            }

            $html .= "</div>";

            if ($output) {
                echo $html;
                return null;
            } else {
                return $html;
            }
        }
    }
}

if (!function_exists('pdd_sql')) {
    /**
     * Dump SQL and die.
     *
     * @param string $sql SQL query
     * @param array<mixed> $bindings Optional parameter bindings
     * @param \PDO|null $pdo Optional PDO connection for EXPLAIN
     * @param array<string, mixed> $options Formatter configuration options
     */
    function pdd_sql(string $sql, array $bindings = [], ?\PDO $pdo = null, array $options = []): void
    {
        pd_sql($sql, $bindings, $pdo, $options);
        exit(0);
    }
}

if (!function_exists('pd_query')) {
    /**
     * Alias for pd_sql.
     *
     * @param string $sql SQL query
     * @param array<mixed> $bindings Optional parameter bindings
     * @param \PDO|null $pdo Optional PDO connection for EXPLAIN
     * @param array<string, mixed> $options Formatter configuration options
     * @param bool $output When true (default) the dump is written directly, otherwise returned
     * @return string|null
     */
    function pd_query(string $sql, array $bindings = [], ?\PDO $pdo = null, array $options = [], bool $output = true): ?string
    {
        return pd_sql($sql, $bindings, $pdo, $options, $output);
    }
}
