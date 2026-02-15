#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper JSON Detection Demo\n";
echo str_repeat("=", 60) . "\n\n";

$configuration = new FormatterConfiguration([
    'maxDepth' => 4,
    'showContext' => false,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter, stream_isatty(STDOUT));

echo "📋 Example 1: JSON auto-detection with dumpj()\n";
echo str_repeat("-", 40) . "\n";
$jsonData1 = ['name' => 'Alice', 'age' => 30, 'skills' => ['PHP', 'JavaScript']];
dumpj($jsonData1);

echo "\n📋 Example 2: JSON string with pd() + autoDetectJson option\n";
echo str_repeat("-", 40) . "\n";
$jsonString2 = '{"users":[{"id":1,"name":"Bob"},{"id":2,"name":"Charlie"}],"count":2}';
pd($jsonString2, ['autoDetectJson' => true]);

echo "\n📋 Example 3: JSON in array\n";
echo str_repeat("-", 40) . "\n";
$mixedData = [
    'user' => '{"id":1,"name":"David","email":"david@example.com"}',
    'config' => '{"theme":"dark","language":"zh","notifications":true}',
    'status' => 200,
];
$request = new DumpRenderRequest($mixedData, 'cli', ['autoDetectJson' => true]);
echo $renderer->render($request);

echo "\n📋 Example 4: Complex nested JSON\n";
echo str_repeat("-", 40) . "\n";
$complexJson = '{
    "company": {
        "name": "TechCorp",
        "employees": [
            {"id":1,"name":"Alice","department":"Engineering"},
            {"id":2,"name":"Bob","department":"Marketing"}
        ],
        "locations": [
            {"city":"Beijing","country":"China","active":true},
            {"city":"Shanghai","country":"China","active":true}
        ]
    },
    "metadata": {
        "version":"2.0.0",
        "created_at":"2024-01-15T10:30:00Z",
        "features":["api","web","mobile"]
    }
}';
pd($complexJson, ['autoDetectJson' => true]);

echo "\n📋 Example 5: JSON array with multiple items\n";
echo str_repeat("-", 40) . "\n";
$productsJson = '[
    {"id":101,"name":"Laptop","price":999.99,"in_stock":true},
    {"id":102,"name":"Mouse","price":29.99,"in_stock":true},
    {"id":103,"name":"Keyboard","price":79.99,"in_stock":false}
]';
dumpj($productsJson);

echo "\n📋 Example 6: JSON with special characters\n";
echo str_repeat("-", 40) . "\n";
$specialJson = '{"message":"Hello 世界!","emoji":"🎉","quote":"It\'s working","backslash":"C:\\\\path"}';
pd($specialJson, ['autoDetectJson' => true]);

echo "\n📋 Example 7: Non-JSON string (regular string)\n";
echo str_repeat("-", 40) . "\n";
$nonJson = 'This is just a regular string that looks like JSON: { key: value } but it is not';
pd($nonJson);

echo "\n📋 Example 8: Invalid JSON string (falls back to string)\n";
echo str_repeat("-", 40) . "\n";
$invalidJson = '{"incomplete": true, "missing": }';
pd($invalidJson, ['autoDetectJson' => true]);

echo "\n📋 Example 9: JSON with numbers and booleans\n";
echo str_repeat("-", 40) . "\n";
$typedJson = '{"count":42,"ratio":3.14159,"active":true,"deleted":null,"score":98.5}';
pd($typedJson, ['autoDetectJson' => true]);

echo "\n📋 Example 10: Empty JSON objects and arrays\n";
echo str_repeat("-", 40) . "\n";
$emptyJson = '{"user":{},"settings":[],"meta":{"empty_obj":{},"empty_arr":[]}}';
pd($emptyJson, ['autoDetectJson' => true]);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ JSON auto-detection demo completed!\n\n";

echo "🎯 Key features demonstrated:\n";
echo "1. ✅ Automatic JSON detection with dumpj()\n";
echo "2. ✅ Manual JSON detection with autoDetectJson option\n";
echo "3. ✅ JSON syntax highlighting (keys, strings, numbers, booleans)\n";
echo "4. ✅ Support for complex nested structures\n";
echo "5. ✅ Unicode and emoji support\n";
echo "6. ✅ Graceful fallback for invalid JSON\n";
echo "7. ✅ Works seamlessly in arrays and objects\n\n";

echo "💡 Usage:\n";
echo "- dumpj(\$value) - Auto-detect and format JSON\n";
echo "- pd(\$jsonString, ['autoDetectJson' => true]) - Manual enable\n";
echo "- ddj(\$value) - Dump JSON and exit\n";
echo "- pdj(\$value) - Alias for dumpj with more options\n\n";
