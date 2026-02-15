#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper Diff Comparison Demo\n";
echo str_repeat("=", 60) . "\n\n";

$configuration = new FormatterConfiguration([
    'maxDepth' => 4,
    'showContext' => false,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter, stream_isatty(STDOUT));

echo "📋 Example 1: Simple array diff\n";
echo str_repeat("-", 40) . "\n";
$oldData1 = ['name' => 'John', 'age' => 30, 'city' => 'New York'];
$newData1 = ['name' => 'John', 'age' => 31, 'city' => 'San Francisco', 'country' => 'USA'];
pd_diff($oldData1, $newData1);

echo "\n📋 Example 2: Nested structure diff\n";
echo str_repeat("-", 40) . "\n";
$oldData2 = [
    'user' => [
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'roles' => ['admin'],
    ],
    'settings' => ['theme' => 'light', 'notifications' => true],
];
$newData2 = [
    'user' => [
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice.new@example.com',
        'roles' => ['admin', 'editor'],
    ],
    'settings' => ['theme' => 'dark', 'notifications' => false, 'language' => 'en'],
    'active' => true,
];
pd_diff($oldData2, $newData2);

echo "\n📋 Example 3: JSON string diff (auto-detection)\n";
echo str_repeat("-", 40) . "\n";
$oldJson = '{"name":"Bob","age":25,"skills":["PHP","Laravel"]}';
$newJson = '{"name":"Bob","age":26,"skills":["PHP","Laravel","Vue.js"]}';
pd_diff($oldJson, $newJson);

echo "\n📋 Example 4: Diff with large arrays\n";
echo str_repeat("-", 40) . "\n";
$oldProducts = [
    ['id' => 1, 'name' => 'Product A', 'price' => 99.99, 'in_stock' => true],
    ['id' => 2, 'name' => 'Product B', 'price' => 149.99, 'in_stock' => true],
];
$newProducts = [
    ['id' => 1, 'name' => 'Product A', 'price' => 99.99, 'in_stock' => false],
    ['id' => 2, 'name' => 'Product B', 'price' => 129.99, 'in_stock' => true],
    ['id' => 3, 'name' => 'Product C', 'price' => 199.99, 'in_stock' => true],
];
pd_diff($oldProducts, $newProducts);

echo "\n📋 Example 5: Object properties diff\n";
echo str_repeat("-", 40) . "\n";
class User {
    public function __construct(
        public string $name,
        public int $age,
        public array $roles = [],
    ) {}
}

$oldUser = new User('Charlie', 25, ['developer']);
$newUser = new User('Charlie', 26, ['developer', 'reviewer']);
pd_diff($oldUser, $newUser);

echo "\n📋 Example 6: Mixed data types diff\n";
echo str_repeat("-", 40) . "\n";
$oldMixed = [
    'string' => 'hello',
    'number' => 42,
    'boolean' => true,
    'null_value' => null,
    'array' => [1, 2, 3],
];
$newMixed = [
    'string' => 'hello world',
    'number' => 43,
    'boolean' => false,
    'array' => [1, 2, 3, 4],
    'new_field' => 'added',
];
pd_diff($oldMixed, $newMixed);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Diff comparison demo completed!\n\n";

echo "🎯 Key features demonstrated:\n";
echo "1. ✅ Automatic detection of JSON strings\n";
echo "2. ✅ Clear visual indication of changes:\n";
echo "   🟢 Added items\n";
echo "   🔴 Removed items\n";
echo "   🟡 Modified values\n";
echo "   ⚪ Unchanged values\n";
echo "3. ✅ Support for nested structures\n";
echo "4. ✅ Works with arrays, objects, and mixed types\n";
echo "5. ✅ Compatible with CLI and Web environments\n\n";

echo "💡 Usage:\n";
echo "- pd_diff(\$oldValue, \$newValue) - Compare two values\n";
echo "- pd_auto_diff(\$newValue) - Compare with last dumped value at same location\n";
echo "- pdd_diff(\$oldValue, \$newValue) - Diff and exit\n";
echo "- pd_clear_history() - Clear diff history\n\n";
