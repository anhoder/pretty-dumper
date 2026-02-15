<?php

declare(strict_types=1);

/**
 * PrettyDumper CLI Basic Usage Example
 *
 * Run: php examples/cli/basic-usage.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "=== PrettyDumper CLI Basic Example ===\n\n";

// Basic configuration
$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'maxItems' => 50,
    'showContext' => true,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter);

// Example 1: Simple array
echo "1. Simple array:\n";
$simpleArray = ['name' => 'John', 'age' => 30, 'city' => 'New York'];
$request = new DumpRenderRequest($simpleArray, 'cli');
echo $renderer->render($request);
echo "\n";

// Example 2: Nested data structure
echo "2. Nested data structure:\n";
$nestedData = [
    'user' => [
        'id' => 1,
        'profile' => [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ],
    ],
    'posts' => [
        ['id' => 1, 'title' => 'First Post'],
        ['id' => 2, 'title' => 'Second Post'],
    ],
];
$request = new DumpRenderRequest($nestedData, 'cli');
echo $renderer->render($request);
echo "\n";

// Example 3: Objects and class instances
echo "3. Objects and class instances:\n";
class User {
    public function __construct(
        public string $name,
        public int $age,
        public array $roles = []
    ) {}
}

$user = new User('Bob', 25, ['admin', 'editor']);
$request = new DumpRenderRequest($user, 'cli');
echo $renderer->render($request);
echo "\n";

// Example 4: Large data structure (truncation demo)
echo "4. Large data structure (truncation demo):\n";
$largeArray = [];
for ($i = 0; $i < 100; $i++) {
    $largeArray["item_$i"] = [
        'id' => $i,
        'data' => str_repeat('x', 100),
        'nested' => ['a' => 1, 'b' => 2, 'c' => 3],
    ];
}
$request = new DumpRenderRequest($largeArray, 'cli');
echo $renderer->render($request);
echo "\n";

echo "=== Examples completed ===\n";
