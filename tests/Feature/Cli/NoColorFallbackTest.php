<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

it('falls back to plain text when color disabled', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());
    $renderer = new CliRenderer($formatter);

    $request = new DumpRenderRequest(['status' => 'ok'], 'cli', [
        'color' => false,
    ]);

    $output = $renderer->render($request);

    expect($output)
        ->toContain('array(1)')
        ->toContain("['status'] => string(2) \"ok\"")
        ->not->toContain("\e[");
});

it('detects non tty streams and removes ansi sequences', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());
    $renderer = new CliRenderer($formatter, streamIsTty: false);

    $request = new DumpRenderRequest(['status' => 'ok'], 'cli');
    $output = $renderer->render($request);

    expect($output)->not->toContain("\e[");
});
