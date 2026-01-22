<?php

declare(strict_types=1);

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

// Test classes to demonstrate parent private properties
class ParentClass
{
    private string $parentPrivate = 'parent-private-value';
    protected string $parentProtected = 'parent-protected-value';
    public string $parentPublic = 'parent-public-value';
}

class ChildClass extends ParentClass
{
    private string $childPrivate = 'child-private-value';
    protected string $childProtected = 'child-protected-value';
    public string $childPublic = 'child-public-value';
}

class GrandchildClass extends ChildClass
{
    private string $grandchildPrivate = 'grandchild-private-value';
    public string $grandchildPublic = 'grandchild-public-value';
}

it('displays parent class private properties', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'maxDepth' => 3,
    ]));

    $renderer = new CliRenderer($formatter);

    $object = new ChildClass();
    $request = new DumpRenderRequest($object, 'cli');
    $output = $renderer->render($request);

    // Strip ANSI codes for easier matching
    $cleanOutput = preg_replace('/\e\[[0-9;]*m/', '', $output);

    // Should contain parent's private property
    expect($cleanOutput)
        ->toContain('parentPrivate:private(ParentClass)')
        ->toContain('parent-private-value')
        ->toContain('parentProtected:protected')
        ->toContain('parent-protected-value')
        ->toContain('parentPublic:public')
        ->toContain('parent-public-value')
        ->toContain('childPrivate:private(ChildClass)')
        ->toContain('child-private-value');
});

it('displays multiple levels of parent private properties', function (): void {
    $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration([
        'maxDepth' => 3,
    ]));

    $renderer = new CliRenderer($formatter);

    $object = new GrandchildClass();
    $request = new DumpRenderRequest($object, 'cli');
    $output = $renderer->render($request);

    // Strip ANSI codes for easier matching
    $cleanOutput = preg_replace('/\e\[[0-9;]*m/', '', $output);

    // Should contain all three levels of private properties
    expect($cleanOutput)
        ->toContain('parentPrivate:private(ParentClass)')
        ->toContain('parent-private-value')
        ->toContain('childPrivate:private(ChildClass)')
        ->toContain('child-private-value')
        ->toContain('grandchildPrivate:private(GrandchildClass)')
        ->toContain('grandchild-private-value');
});
