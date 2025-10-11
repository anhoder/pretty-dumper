<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Anhoder\PrettyDumper\Renderer\WebRenderer;
use Anhoder\PrettyDumper\Support\ThemeRegistry;

if (!function_exists('__pretty_dumper_detect_channel')) {
    function __pretty_dumper_detect_channel(): string
    {
        return \in_array(PHP_SAPI, ['cli', 'phpdbg'], true) ? 'cli' : 'web';
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

        $configuration = new FormatterConfiguration($options);
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

        foreach ($values as $value) {
            pretty_dump($value);
        }
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): void
    {
        dump(...$values);
        exit(1);
    }
}

if (!function_exists('pdd')) {
    /**
     * @param array<string, mixed> $options
     */
    function pdd(mixed $value, array $options = [], bool $output = true): void
    {
        pd($value, $options, $output);
        exit(1);
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

        foreach ($values as $value) {
            pretty_dump(__pretty_dumper_json_stringify($value), ['autoDetectJson' => true]);
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
        exit(1);
    }
}

if (!function_exists('pddj')) {
    /**
     * @param array<string, mixed> $options
     */
    function pddj(mixed $value, array $options = [], bool $output = true): void
    {
        pdj($value, $options, $output);
        exit(1);
    }
}
