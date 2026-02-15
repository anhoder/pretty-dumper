#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper Conditional Dump Demo\n";
echo str_repeat("=", 60) . "\n\n";

$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'showContext' => false,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter, stream_isatty(STDOUT));

echo "📋 Example 1: pd_when - Dump when condition is true\n";
echo str_repeat("-", 40) . "\n";
$value1 = ['user' => 'Alice', 'role' => 'admin'];
$shouldDump1 = true;
pd_when($value1, $shouldDump1);

echo "\n📋 Example 2: pd_when - Don't dump when condition is false\n";
echo str_repeat("-", 40) . "\n";
$value2 = ['user' => 'Bob', 'role' => 'guest'];
$shouldDump2 = false;
pd_when($value2, $shouldDump2);
echo "(Nothing should be dumped above because condition is false)\n";

echo "\n📋 Example 3: pd_when with callable condition\n";
echo str_repeat("-", 40) . "\n";
$value3 = ['count' => 150, 'threshold' => 100];
pd_when($value3, fn($data) => $data['count'] > $data['threshold']);

echo "\n📋 Example 4: pd_when - Dump only errors\n";
echo str_repeat("-", 40) . "\n";
$errorResponse = ['status' => 'error', 'message' => 'Invalid request', 'code' => 400];
pd_when($errorResponse, fn($data) => isset($data['status']) && $data['status'] === 'error');

echo "\n📋 Example 5: pd_when - Conditional debug output\n";
echo str_repeat("-", 40) . "\n";
$configData = [
    'app_name' => 'MyApp',
    'debug_mode' => true,
    'api_key' => 'secret-key-123',
    'max_retries' => 3,
];
pd_when($configData, fn($data) => $data['debug_mode'] ?? false);

echo "\n📋 Example 6: pd_assert - Assertion passed\n";
echo str_repeat("-", 40) . "\n";
$value6 = ['status' => 200, 'data' => ['id' => 1, 'name' => 'Alice']];
pd_assert($value6, fn($data) => isset($data['status']) && $data['status'] === 200, 'HTTP status should be 200');

echo "\n📋 Example 7: pd_assert - Assertion failed\n";
echo str_repeat("-", 40) . "\n";
$value7 = ['total' => 100, 'count' => 95];
pd_assert($value7, fn($data) => $data['total'] === $data['count'], 'Total should equal count');

echo "\n📋 Example 8: pd_assert with multiple conditions\n";
echo str_repeat("-", 40) . "\n";
$userData = [
    'name' => 'Charlie',
    'age' => 25,
    'email' => 'charlie@example.com',
    'active' => true,
];
$assertion = fn($data) => 
    isset($data['name'], $data['age'], $data['email']) && 
    $data['age'] >= 18 && 
    $data['age'] <= 100 &&
    filter_var($data['email'], FILTER_VALIDATE_EMAIL) !== false
;
pd_assert($userData, $assertion, 'User data validation');

echo "\n📋 Example 9: pdd_when - Dump and die when condition met\n";
echo str_repeat("-", 40) . "\n";
$value9 = ['error' => 'Critical system failure', 'code' => 500];
$shouldDie = true;
echo "This will dump and exit (not shown in demo):\n";
// pdd_when($value9, $shouldDie); // Commented to prevent actual exit

echo "\n📋 Example 10: Conditional dump in loop\n";
echo str_repeat("-", 40) . "\n";
$items = [
    ['id' => 1, 'name' => 'Item A', 'price' => 99.99],
    ['id' => 2, 'name' => 'Item B', 'price' => 149.99],
    ['id' => 3, 'name' => 'Item C', 'price' => 0.01],
    ['id' => 4, 'name' => 'Item D', 'price' => -10.00],
];
foreach ($items as $item) {
    pd_when($item, fn($data) => $data['price'] < 0 || $data['price'] > 100);
}

echo "\n📋 Example 11: pd_assert for data type checking\n";
echo str_repeat("-", 40) . "\n";
$apiResponse = ['users' => [['id' => 1], ['id' => 2]]];
pd_assert($apiResponse, fn($data) => is_array($data['users'] ?? null), 'Users should be an array');

echo "\n📋 Example 12: pd_assert with boolean condition\n";
echo str_repeat("-", 40) . "\n";
$systemStatus = ['cpu' => 45, 'memory' => 70, 'disk' => 90];
pd_assert($systemStatus, fn($data) => $data['cpu'] < 80 && $data['memory'] < 80, 'System resources should be below 80%');

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Conditional dump demo completed!\n\n";

echo "🎯 Key features demonstrated:\n";
echo "1. ✅ pd_when(\$value, \$condition) - Conditional dumping\n";
echo "2. ✅ Supports boolean conditions\n";
echo "3. ✅ Supports callable conditions for complex logic\n";
echo "4. ✅ pd_assert(\$value, \$assertion, \$message) - Assertion-based dumping\n";
echo "5. ✅ Clear warning display when assertions fail\n";
echo "6. ✅ Useful for debugging, validation, and monitoring\n";
echo "7. ✅ pdd_when() and pdd_assert() variants to exit after dump\n\n";

echo "💡 Use cases:\n";
echo "- Debug only when debug mode is enabled\n";
echo "- Dump errors but not successful responses\n";
echo "- Validate data invariants and catch violations\n";
echo "- Monitor system health conditions\n";
echo "- Trace execution flow with checkpoints\n\n";
