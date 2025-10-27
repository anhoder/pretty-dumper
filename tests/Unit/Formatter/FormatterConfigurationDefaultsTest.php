<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;

if (!function_exists('withPrettyDumpEnv')) {
    /**
     * @param callable():void $callback
     */
    function withPrettyDumpEnv(?string $value, callable $callback): void
    {
        $previous = getenv('PRETTY_DUMP_SHOW_CONTEXT');
        $envExists = array_key_exists('PRETTY_DUMP_SHOW_CONTEXT', $_ENV);
        $envValue = $envExists ? $_ENV['PRETTY_DUMP_SHOW_CONTEXT'] : null;
        $serverExists = array_key_exists('PRETTY_DUMP_SHOW_CONTEXT', $_SERVER);
        $serverValue = $serverExists ? $_SERVER['PRETTY_DUMP_SHOW_CONTEXT'] : null;

        if ($value === null) {
            putenv('PRETTY_DUMP_SHOW_CONTEXT');
            unset($_ENV['PRETTY_DUMP_SHOW_CONTEXT'], $_SERVER['PRETTY_DUMP_SHOW_CONTEXT']);
        } else {
            putenv('PRETTY_DUMP_SHOW_CONTEXT=' . $value);
            $_ENV['PRETTY_DUMP_SHOW_CONTEXT'] = $value;
            $_SERVER['PRETTY_DUMP_SHOW_CONTEXT'] = $value;
        }

        try {
            $callback();
        } finally {
            if ($previous !== false) {
                putenv('PRETTY_DUMP_SHOW_CONTEXT=' . (string) $previous);
            } else {
                putenv('PRETTY_DUMP_SHOW_CONTEXT');
            }

            if ($envExists) {
                $_ENV['PRETTY_DUMP_SHOW_CONTEXT'] = $envValue;
            } else {
                unset($_ENV['PRETTY_DUMP_SHOW_CONTEXT']);
            }

            if ($serverExists) {
                $_SERVER['PRETTY_DUMP_SHOW_CONTEXT'] = $serverValue;
            } else {
                unset($_SERVER['PRETTY_DUMP_SHOW_CONTEXT']);
            }
        }
    }
}

it('defaults to hiding context in cli environments', function (): void {
    withPrettyDumpEnv(null, function (): void {
        $configuration = new FormatterConfiguration();

        expect($configuration->showContext())->toBeFalse();
    });
});

it('allows enabling context via environment variable', function (): void {
    withPrettyDumpEnv('1', function (): void {
        $configuration = new FormatterConfiguration();

        expect($configuration->showContext())->toBeTrue();
    });
});
