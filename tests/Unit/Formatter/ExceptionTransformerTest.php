<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;

beforeEach(function (): void {
    $this->formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'expandExceptions' => true,
    ]));
});

it('formats single exception with highlighted message and location', function (): void {
    $exception = new RuntimeException('Sensitive token=abcd1234', 1001);
    $request = new DumpRenderRequest($exception, 'cli');

    $segment = $this->formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->toBeInstanceOf(RenderedSegment::class)
        ->and($payloadSegment->type())->toBe('exception');
    expect($payloadSegment->content())
        ->toContain('RuntimeException')
        ->toContain('code: 1001')
        ->toContain('Sensitive token=***');
});

it('renders chained exceptions with caused-by sections', function (): void {
    $inner = new InvalidArgumentException('Invalid payload size', 422);
    $outer = new RuntimeException('Failed to transform payload', 500, $inner);

    $request = new DumpRenderRequest($outer, 'cli');
    $segment = $this->formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->not->toBeNull();

    expect($payloadSegment->content())
        ->toContain('Caused by')
        ->toContain('InvalidArgumentException')
        ->toContain('RuntimeException');
});

it('renders verbose exception messages in full', function (): void {
    $longMessage = str_repeat('Stack overflow detected. ', 20);
    $exception = new OverflowException($longMessage);

    $request = new DumpRenderRequest($exception, 'cli', [
        'messageLimit' => 120,
    ]);

    $segment = $this->formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->not->toBeNull();

    expect($payloadSegment->content())
        ->not->toContain('… truncated')
        ->toContain($longMessage);
});
