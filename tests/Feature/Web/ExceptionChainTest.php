<?php

declare(strict_types=1);

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\WebRenderer;
use PrettyDumper\Support\ThemeRegistry;

it('renders exception chain with collapsible details', function (): void {
    $inner = new InvalidArgumentException('Invalid email address provided.');
    $outer = new RuntimeException('User registration failed', 0, $inner);

    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'expandExceptions' => true,
    ]));
    $renderer = new WebRenderer($formatter, $registry);

    $request = new DumpRenderRequest($outer, 'web');
    $html = $renderer->render($request);

    expect($html)
        ->toContain('data-node-type="exception"')
        ->toContain('Caused by')
        ->toContain('<details')
        ->toContain('aria-label="Exception trace"');
});

it('includes context snapshot summary with redacted values', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'showContext' => true,
    ]));
    $renderer = new WebRenderer($formatter, $registry);

    $request = new DumpRenderRequest(new RuntimeException('Boom'), 'web', [
        'context' => [
            'request' => ['password' => 'abc'],
        ],
    ]);

    $html = $renderer->render($request);

    expect($html)
        ->toContain('Context')
        ->toContain('***');
});
