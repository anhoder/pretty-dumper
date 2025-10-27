<?php

declare(strict_types=1);

it('dump hides context by default in cli', function (): void {
    $script = "require 'vendor/autoload.php'; dump('first', 'second');";
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
    $output = shell_exec($command);

    expect($output)
        ->not()->toBeNull()
        ->toContain('"first"')
        ->toContain('"second"');

    expect(substr_count((string) $output, 'Context:'))->toBe(0);
    expect(substr_count((string) $output, 'Rendered in'))->toBe(0);
});

it('dump outputs context once when enabled via environment variable', function (): void {
    $script = "putenv('PRETTY_DUMP_SHOW_CONTEXT=1'); \$_ENV['PRETTY_DUMP_SHOW_CONTEXT'] = '1'; \$_SERVER['PRETTY_DUMP_SHOW_CONTEXT'] = '1'; require 'vendor/autoload.php'; dump('first', 'second');";
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
    $output = shell_exec($command);

    expect($output)
        ->not()->toBeNull()
        ->toContain('"first"')
        ->toContain('"second"');

    expect(substr_count((string) $output, 'Context:'))->toBe(1);
    expect(substr_count((string) $output, 'Rendered in'))->toBe(0);
});

it('dumpj hides context by default in cli', function (): void {
    $script = "require 'vendor/autoload.php'; dumpj(['first' => 1], ['second' => 2]);";
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
    $output = shell_exec($command);

    expect($output)
        ->not()->toBeNull();

    expect(substr_count((string) $output, 'JSON Document'))->toBe(2);
    expect(substr_count((string) $output, 'Context:'))->toBe(0);
    expect(substr_count((string) $output, 'Rendered in'))->toBe(0);
});

it('dumpj outputs context once when enabled via environment variable', function (): void {
    $script = "putenv('PRETTY_DUMP_SHOW_CONTEXT=1'); \$_ENV['PRETTY_DUMP_SHOW_CONTEXT'] = '1'; \$_SERVER['PRETTY_DUMP_SHOW_CONTEXT'] = '1'; require 'vendor/autoload.php'; dumpj(['first' => 1], ['second' => 2]);";
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
    $output = shell_exec($command);

    expect($output)
        ->not()->toBeNull();

    expect(substr_count((string) $output, 'JSON Document'))->toBe(2);
    expect(substr_count((string) $output, 'Context:'))->toBe(1);
    expect(substr_count((string) $output, 'Rendered in'))->toBe(0);
});
