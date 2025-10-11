<?php

declare(strict_types=1);

use PrettyDumper\Context\Collectors\ContextCollector;
use PrettyDumper\Context\ContextFrame;
use PrettyDumper\Context\ContextSnapshot;
use PrettyDumper\Context\RedactionRule;
use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PHPUnit\Framework\Attributes\Override;

class FakeCollector implements ContextCollector
{
    #[Override]
    public function collect(DumpRenderRequest $request): ContextSnapshot
    {
        return new ContextSnapshot(
            origin: ['file' => __FILE__, 'line' => __LINE__],
            stack: [
                new ContextFrame(
                    __FILE__,
                    __LINE__,
                    __FUNCTION__,
                    ['password' => 'super-secret'],
                ),
            ],
            request: ['password' => 'super-secret'],
            env: ['API_KEY' => 'abcd-1234'],
        );
    }
}

it('collects stack frames and applies redaction rules', function (): void {
    $collector = new FakeCollector();
    $configuration = new FormatterConfiguration([
        'redactionRules' => [
            RedactionRule::forPattern('/password/i'),
            RedactionRule::forPattern('/api_key/i'),
        ],
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration, $collector);
    $request = new DumpRenderRequest(['foo' => 'bar'], 'cli');

    $segment = $formatter->format($request);

    $contextSegment = $segment->findChildByType('context');

    expect($contextSegment->content())
        ->not->toContain('super-secret')
        ->and($contextSegment->content())
        ->toContain('***');
});

it('limits stack depth based on configuration', function (): void {
    $collector = new class implements ContextCollector {
        #[Override]
        public function collect(DumpRenderRequest $request): ContextSnapshot
        {
            $frames = [];
            for ($i = 0; $i < 10; $i++) {
                $frames[] = new ContextFrame(
                    __FILE__,
                    100 + $i,
                    'fn' . $i,
                    [],
                );
            }

            return new ContextSnapshot(
                origin: ['file' => __FILE__, 'line' => __LINE__],
                stack: $frames,
                request: [],
                env: [],
            );
        }
    };

    $configuration = new FormatterConfiguration([
        'stackLimit' => 5,
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration, $collector);
    $request = new DumpRenderRequest(['foo' => 'bar'], 'cli');

    $segment = $formatter->format($request);
    $contextSegment = $segment->findChildByType('context');

    expect($contextSegment->metadata())
        ->toHaveKey('truncatedStack')
        ->and($contextSegment->metadata()['truncatedStack'])
        ->toBeTrue();
});
