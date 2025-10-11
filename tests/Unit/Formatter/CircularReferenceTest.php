<?php

declare(strict_types=1);

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Formatter\PrettyFormatter;

it('replaces circular array references with a placeholder', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());

    $data = [];
    $data['self'] = &$data;

    $segment = $formatter->format(new DumpRenderRequest($data, 'cli'));
    $circular = $segment->findChildByType('circular');

    expect($circular)->not->toBeNull();
    expect($circular->content())->toBe('[circular reference]');
});

it('detects circular object graphs', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration());

    $parent = new stdClass();
    $child = new stdClass();
    $parent->child = $child;
    $child->parent = $parent;

    $segment = $formatter->format(new DumpRenderRequest($parent, 'cli'));
    $circular = $segment->findChildByType('circular');

    expect($circular)->not->toBeNull();
    expect($circular->content())->toBe('[circular object]');
});
