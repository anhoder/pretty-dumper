<?php

declare(strict_types=1);

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\CliRenderer;

it('renders one hundred thousand elements within three seconds', function (): void {
    $data = range(1, 100_000);

    $configuration = new FormatterConfiguration([
        'maxDepth' => 3,
        'maxItems' => 100_000,
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);
    $request = new DumpRenderRequest($data, 'cli');

    $start = hrtime(true);
    $renderer->render($request);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    expect($elapsedMs)->toBeLessThanOrEqual(3000);
})->group('performance');
