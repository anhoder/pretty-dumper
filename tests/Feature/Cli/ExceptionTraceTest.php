<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

it('renders exception traces with caused by sections', function (): void {
    $inner = new InvalidArgumentException('Inner failure');
    $outer = new RuntimeException('Outer failure', 0, $inner);

    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'expandExceptions' => true,
    ]));

    $renderer = new CliRenderer($formatter);
    $request = new DumpRenderRequest($outer, 'cli');
    $output = $renderer->render($request);

    expect($output)
        ->toContain('Exception: RuntimeException')
        ->toContain('Caused by: InvalidArgumentException')
        ->toMatch('/Trace:\\n\s+#0/');
});

it('includes variable snapshot summaries in the trace output', function (): void {
    $inner = new LogicException('Computation failed');
    $outer = new RuntimeException('Wrapper', 100, $inner);

    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'includeVariableSnapshots' => true,
    ]));
    $renderer = new CliRenderer($formatter);

    $request = new DumpRenderRequest($outer, 'cli', [
        'context' => [
            'variables' => ['payload' => ['password' => 'secret']],
        ],
    ]);

    $output = $renderer->render($request);

    expect($output)
        ->toContain('payload')
        ->toContain('***');
});
