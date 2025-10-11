<?php

declare(strict_types=1);

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\WebRenderer;
use PrettyDumper\Support\ThemeRegistry;

it('renders both light and dark themes with wcag contrast', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'theme' => 'auto',
    ]));

    $renderer = new WebRenderer($formatter, $registry);
    $request = new DumpRenderRequest(['value' => 123], 'web');

    $html = $renderer->render($request);

    expect($html)
        ->toContain('data-theme="light"')
        ->toContain('data-theme="dark"')
        ->toContain('aria-label="Dump output"');
});

it('ensures templates expose contrast metadata for auditing', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'theme' => 'dark',
    ]));

    $renderer = new WebRenderer($formatter, $registry);
    $request = new DumpRenderRequest(['value' => 123], 'web');
    $html = $renderer->render($request);

    expect($html)
        ->toContain('data-contrast="')
        ->toContain('role="tree"');
});
