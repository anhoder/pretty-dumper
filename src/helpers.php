<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Anhoder\PrettyDumper\Renderer\WebRenderer;
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
