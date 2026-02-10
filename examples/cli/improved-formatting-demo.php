#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Improved Formatting Features Demo
 *
 * Demonstrates new indent configuration, color distinction, and visual improvements
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper Improved Formatting Features Demo\n";
echo str_repeat("=", 60) . "\n\n";

// Sample data
$sampleData = [
    'company' => [
        'name' => 'TechCorp',
        'employees' => [
            [
                'id' => 1,
                'name' => 'Alice',
                'position' => 'Senior Developer',
                'skills' => ['PHP', 'Laravel', 'Vue.js', 'MySQL'],
                'active' => true,
                'salary' => 15000.50,
                'join_date' => '2023-01-15',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'position' => 'DevOps Engineer',
                'skills' => ['Docker', 'Kubernetes', 'AWS'],
                'active' => false,
                'salary' => 18000.75,
                'join_date' => '2022-08-20',
            ],
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => [
                'email' => true,
                'push' => false,
                'sms' => null,
            ],
            'limits' => [
                'max_users' => 1000,
                'max_storage_gb' => 500,
            ],
        ],
    ],
    'metadata' => [
        'version' => '2.0.0',
        'last_updated' => date('Y-m-d H:i:s'),
        'debug_mode' => true,
    ],
];

// Demo 1: Default configuration (4-space indent)
echo "📋 Demo 1: Default configuration (4-space indent)\n";
echo str_repeat("-", 40) . "\n";

$defaultConfig = new FormatterConfiguration();
$formatter1 = PrettyFormatter::forChannel('cli', $defaultConfig);
$renderer1 = new CliRenderer($formatter1);
$request1 = new DumpRenderRequest($sampleData, 'cli');
echo $renderer1->render($request1);
echo "\n\n";

// Demo 2: 2-space indent - more compact
echo "📋 Demo 2: 2-space indent (more compact)\n";
echo str_repeat("-", 40) . "\n";

$compactConfig = new FormatterConfiguration([
    'indentSize' => 2,
    'indentStyle' => 'spaces',
]);
$formatter2 = PrettyFormatter::forChannel('cli', $compactConfig);
$renderer2 = new CliRenderer($formatter2);
$request2 = new DumpRenderRequest($sampleData, 'cli');
echo $renderer2->render($request2);
echo "\n\n";

// Demo 3: Tab indent
echo "📋 Demo 3: Tab indent\n";
echo str_repeat("-", 40) . "\n";

$tabConfig = new FormatterConfiguration([
    'indentStyle' => 'tabs',
]);
$formatter3 = PrettyFormatter::forChannel('cli', $tabConfig);
$renderer3 = new CliRenderer($formatter3);
$request3 = new DumpRenderRequest($sampleData, 'cli');
echo $renderer3->render($request3);
echo "\n\n";

// Demo 4: Color distinction for different data types
echo "📋 Demo 4: Color distinction for various data types\n";
echo str_repeat("-", 40) . "\n";

$typeDemoData = [
    'string' => 'Hello World',
    'integer' => 42,
    'float' => 3.14159,
    'boolean_true' => true,
    'boolean_false' => false,
    'null' => null,
    'array' => [1, 2, 3, 4, 5],
    'object' => new stdClass(),
    'nested_structure' => [
        'level1' => [
            'level2' => [
                'level3' => 'Deep value',
            ],
        ],
    ],
];

$formatter4 = PrettyFormatter::forChannel('cli', $defaultConfig);
$renderer4 = new CliRenderer($formatter4);
$request4 = new DumpRenderRequest($typeDemoData, 'cli');
echo $renderer4->render($request4);
echo "\n\n";

// Demo 5: Complex nested structure
echo "📋 Demo 5: Complex nested structure demonstration\n";
echo str_repeat("-", 40) . "\n";

$complexData = [
    'database' => [
        'connections' => [
            'mysql' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'myapp',
                'username' => 'root',
                'password' => 'secret123', // This will be redacted
                'options' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'strict' => true,
                ],
            ],
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => 'redis-pass', // This will be redacted
                'database' => 0,
            ],
        ],
    ],
    'api_keys' => [
        'stripe' => 'sk_test_1234567890abcdef',
        'github' => 'ghp_abcdef1234567890',
        'mailgun' => 'key-1234567890abcdef',
    ],
];

$secureConfig = new FormatterConfiguration([
    'redactionRules' => [
        ['pattern' => '/password/i', 'replacement' => '***'],
        ['pattern' => '/api[_-]?key/i', 'replacement' => '***'],
    ],
]);
$formatter5 = PrettyFormatter::forChannel('cli', $secureConfig);
$renderer5 = new CliRenderer($formatter5);
$request5 = new DumpRenderRequest($complexData, 'cli');
echo $renderer5->render($request5);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Improved formatting features demo completed!\n\n";

echo "🎯 Key improvements summary:\n";
echo "1. ✅ Configurable indentation (space count, tab/space selection)\n";
echo "2. ✅ Enhanced visual distinction (▶/▼ icons, tree lines ├── )\n";
echo "3. ✅ Type-specific color scheme:\n";
echo "   - 🟢 Strings: Green\n";
echo "   - 🟣 Numbers: Magenta\n";
echo "   - 🔵 Booleans: Blue\n";
echo "   - ⚫ Null: Gray\n";
echo "   - 🟠 Arrays: Cyan\n";
echo "   - 🟣 Objects: Bright magenta\n";
echo "4. ✅ Maintains high performance and backward compatibility\n";
echo "5. ✅ Supports automatic sensitive data redaction\n\n";

echo "💡 Usage tips:\n";
echo "- Small screens or compact output: Use 2-space indent\n";
echo "- Standard dev environment: Use default 4-space indent\n";
echo "- Deeply nested data: Consider using tab indent\n";
echo "- Production logs: Use with redaction rules\n";
