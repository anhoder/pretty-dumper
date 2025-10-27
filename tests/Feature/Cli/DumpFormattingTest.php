<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

it('renders nested arrays with indentation and ansi styling', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'maxDepth' => 3,
    ]));

    $renderer = new CliRenderer($formatter);

    $payload = [
        'user' => [
            'id' => 42,
            'roles' => ['admin', 'editor'],
        ],
    ];

    $request = new DumpRenderRequest($payload, 'cli');
    $output = $renderer->render($request);

    expect($output)
        ->toContain('array(1)')
        ->toContain("\e[33m['user']")
        ->toContain("\e[")
        ->toContain('admin');
});

it('displays truncation notice when limits exceeded', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'maxItems' => 1,
    ]));

    $renderer = new CliRenderer($formatter);

    $payload = ['items' => range(1, 5)];
    $request = new DumpRenderRequest($payload, 'cli');
    $output = $renderer->render($request);

    expect($output)
        ->toContain('… truncated (items: 5, limit: 1)');
});

it('renders standalone strings with distinct type and value colors', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'showContext' => false,
    ]));

    $renderer = new CliRenderer($formatter);

    $value = 'text';
    $request = new DumpRenderRequest($value, 'cli', ['color' => true]);
    $output = $renderer->render($request);

    $expected = sprintf("\033[36mstring(%d)\033[0m \033[32m\"%s\"\033[0m", strlen($value), $value);
    expect($output)->toContain($expected);
});

it('keeps string coloring consistent inside arrays', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'showContext' => false,
    ]));

    $renderer = new CliRenderer($formatter);

    $value = 'text';
    $payload = ['label' => $value];
    $request = new DumpRenderRequest($payload, 'cli', ['color' => true]);
    $output = $renderer->render($request);

    $expected = sprintf("\033[36mstring(%d)\033[0m \033[32m\"%s\"\033[0m", strlen($value), $value);
    expect($output)->toContain($expected);
});
