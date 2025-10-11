<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\WebRenderer;
use Anhoder\PrettyDumper\Support\ThemeRegistry;

it('renders details summary structure for no js environments', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'theme' => 'light',
    ]));
    $renderer = new WebRenderer($formatter, $registry);

    $request = new DumpRenderRequest(['payload' => ['foo' => 'bar']], 'web');
    $html = $renderer->render($request, [
        'preferJavascript' => false,
    ]);

    expect($html)
        ->toContain('<details')
        ->toContain('<summary')
        ->toContain('data-depth="0"')
        ->toContain('keyboard-focusable');
});

it('flags truncated nodes with accessible labels', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration([
        'maxItems' => 2,
    ]));
    $renderer = new WebRenderer($formatter, $registry);

    $request = new DumpRenderRequest(['items' => range(1, 10)], 'web');
    $html = $renderer->render($request, [
        'preferJavascript' => false,
    ]);

    expect($html)
        ->toContain('data-truncated="true"')
        ->toContain('aria-live="polite"');
});
