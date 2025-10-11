<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;

it('formats scalar values with type annotations', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());
    $request = new DumpRenderRequest('hello world', 'cli');

    $segment = $formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->toBeInstanceOf(RenderedSegment::class)
        ->and($payloadSegment->content())
        ->toContain('string(11)')
        ->and($payloadSegment->content())
        ->toContain('hello world');
});

it('detects json strings and produces collapsible tree segments', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());
    $json = json_encode(['user' => ['id' => 42, 'name' => 'Ada']], JSON_THROW_ON_ERROR);
    $request = new DumpRenderRequest($json, 'cli', ['autoDetectJson' => true]);

    $segment = $formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->toBeInstanceOf(RenderedSegment::class)
        ->and($payloadSegment->type())->toBe('json');
    expect($payloadSegment->metadata())
        ->toHaveKey('collapsible')
        ->and($payloadSegment->children())
        ->not->toBeEmpty();
});

it('respects depth and length limits for nested arrays', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'maxDepth' => 2,
        'stringLengthLimit' => 8,
    ]));

    $payload = [
        'profile' => [
            'name' => 'Ada Lovelace',
            'bio' => str_repeat('A', 64),
        ],
    ];

    $request = new DumpRenderRequest($payload, 'cli');
    $segment = $formatter->format($request);
    $payloadSegment = $segment->children()[0] ?? null;

    expect($payloadSegment)
        ->toBeInstanceOf(RenderedSegment::class);

    $stringSegment = null;
    foreach ($payloadSegment->children() as $child) {
        $candidate = $child->findChildByType('string');
        if ($candidate !== null) {
            $stringSegment = $candidate;
            break;
        }
    }

    expect($stringSegment)
        ->not->toBeNull()
        ->and($stringSegment->metadata())
        ->toHaveKey('truncated')
        ->and($stringSegment->content())
        ->toContain('… truncated');
});
