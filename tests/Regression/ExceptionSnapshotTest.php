<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

it('matches the expected snapshot for nested exceptions', function (): void {
    $inner = new InvalidArgumentException('Database pool exhausted');
    $outer = new RuntimeException('Background job failed to execute', 0, $inner);

    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'expandExceptions' => true,
        'showContext' => true,
    ]));
    $renderer = new CliRenderer($formatter);

    $request = new DumpRenderRequest($outer, 'cli');
    $output = $renderer->render($request);

    expect($output)
        ->toContain('Exception: RuntimeException')
        ->toContain('Caused by: InvalidArgumentException')
        ->toMatch('/Trace:\\n\s+#0/')
        ->toContain('Rendered in');
});
