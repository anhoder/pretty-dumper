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
