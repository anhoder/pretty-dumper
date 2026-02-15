#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PrettyDumper Example Runner
 *
 * Run: php examples/run-examples.php
 *
 * This script provides an interactive menu to run different examples
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

function displayBanner(): void
{
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                  PrettyDumper Example Runner                 ║\n";
    echo "║                                                              ║\n";
    echo "║  Powerful PHP debugging tool - Beautiful output for vars,   ║\n";
    echo "║             exceptions and stack trace information          ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

function displayMenu(): int
{
    echo "Please select an example to run:\n\n";
    echo "1. CLI Basic Usage Example\n";
    echo "2. CLI Exception Handling Example\n";
    echo "3. Performance Test Example\n";
    echo "4. Configuration Options Demo\n";
    echo "5. Sensitive Data Redaction Demo\n";
    echo "6. Theme Switching Demo\n";
    echo "7. SQL Auto-Detection Demo\n";
    echo "8. JSON Detection Demo\n";
    echo "9. Diff Comparison Demo\n";
    echo "10. Conditional Dump Demo\n";
    echo "11. Context Snapshot Demo\n";
    echo "12. Exit\n\n";

    $input = readline("Please enter option number (1-12): ");
    $choice = $input !== false ? trim($input) : '';
    return (int) $choice;
}

function runBasicExample(): void
{
    echo "\n🚀 Running CLI basic usage example...\n\n";

    $configuration = new FormatterConfiguration([
        'maxDepth' => 3,
        'showContext' => true,
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    // 示例数据
    $sampleData = [
        'application' => [
            'name' => 'PrettyDumper',
            'version' => '1.0.0',
            'description' => 'A beautiful PHP debugging tool',
        ],
        'features' => [
            'CLI support',
            'Web interface',
            'Theme switching',
            'Exception handling',
            'Performance monitoring',
        ],
        'configuration' => [
            'maxDepth' => 3,
            'maxItems' => 100,
            'theme' => 'light',
            'showContext' => true,
        ],
    ];

    $request = new DumpRenderRequest($sampleData, 'cli');
    echo $renderer->render($request);
    echo "\n";
}

function runExceptionExample(): void
{
    echo "\n⚡ Running exception handling example...\n\n";

    $configuration = new FormatterConfiguration([
        'expandExceptions' => true,
        'showContext' => true,
        'theme' => 'dark',
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    try {
        throw new RuntimeException('Database connection failed', 500);
    } catch (\Exception $e) {
        $exceptionData = [
            'error' => $e,
            'context' => [
                'service' => 'database',
                'connection' => 'mysql://localhost:3306',
                'query' => 'SELECT * FROM users WHERE active = ?',
                'params' => [true],
            ],
            'debug' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ],
        ];

        $request = new DumpRenderRequest($exceptionData, 'cli');
        echo $renderer->render($request);
        echo "\n";
    }
}

function runPerformanceExample(): void
{
    echo "\n⚡ Running performance test example...\n\n";

    $configuration = new FormatterConfiguration([
        'maxItems' => 100,
        'performanceThreshold' => 3000,
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    echo "Generating test data...\n";
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    // Generate large dataset
    $largeArray = [];
    for ($i = 0; $i < 1000; $i++) {
        $largeArray[] = [
            'id' => $i,
            'name' => 'Item ' . $i,
            'description' => str_repeat('Lorem ipsum dolor sit amet, ', 5),
            'price' => rand(100, 10000) / 100,
            'metadata' => [
                'created_at' => date('Y-m-d H:i:s'),
                'tags' => array_map(fn() => 'tag_' . rand(1, 50), range(1, 3)),
            ],
        ];
    }

    $generateEndTime = microtime(true);
    echo "Data generation completed, time: " . number_format(($generateEndTime - $startTime) * 1000, 2) . "ms\n";
    echo "Memory usage: " . formatBytes(memory_get_usage(true) - $startMemory) . "\n\n";

    echo "Starting formatting...\n";
    $formatStartTime = microtime(true);

    $request = new DumpRenderRequest($largeArray, 'cli');
    $output = $renderer->render($request);

    $formatEndTime = microtime(true);
    $endMemory = memory_get_usage(true);

    echo "Formatting completed!\n";
    echo "Formatting time: " . number_format(($formatEndTime - $formatStartTime) * 1000, 2) . "ms\n";
    echo "Output length: " . number_format(strlen($output)) . " characters\n";
    echo "Total memory usage: " . formatBytes($endMemory - $startMemory) . "\n\n";

    echo "Output preview (first 1000 characters):\n";
    echo substr($output, 0, 1000) . "...\n";
}

function runConfigurationExample(): void
{
    echo "\n⚙️  Configuration options demo (including new indent and color settings)...\n\n";

    $testData = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'level4' => [
                        'level5' => 'This is deep nested data',
                        'array' => range(1, 20),
                    ],
                ],
            ],
        ],
        'large_string' => str_repeat('Lorem ipsum dolor sit amet, ', 20),
        'metadata' => [
            'created' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'tags' => ['php', 'debugging', 'tools'],
        ],
    ];

    echo "1. Default configuration (4-space indent):\n";
    $defaultConfig = new FormatterConfiguration();
    $formatter1 = PrettyFormatter::forChannel('cli', $defaultConfig);
    $renderer1 = new CliRenderer($formatter1);
    $request1 = new DumpRenderRequest($testData, 'cli');
    echo $renderer1->render($request1);
    echo "\n";

    echo "2. 2-space indent configuration:\n";
    $smallIndentConfig = new FormatterConfiguration([
        'indentSize' => 2,
        'indentStyle' => 'spaces',
    ]);
    $formatter2 = PrettyFormatter::forChannel('cli', $smallIndentConfig);
    $renderer2 = new CliRenderer($formatter2);
    $request2 = new DumpRenderRequest($testData, 'cli');
    echo $renderer2->render($request2);
    echo "\n";

    echo "3. Tab indent configuration:\n";
    $tabIndentConfig = new FormatterConfiguration([
        'indentStyle' => 'tabs',
    ]);
    $formatter3 = PrettyFormatter::forChannel('cli', $tabIndentConfig);
    $renderer3 = new CliRenderer($formatter3);
    $request3 = new DumpRenderRequest($testData, 'cli');
    echo $renderer3->render($request3);
    echo "\n";

    echo "4. Limit depth and item count:\n";
    $limitedConfig = new FormatterConfiguration([
        'maxDepth' => 2,
        'maxItems' => 5,
        'stringLengthLimit' => 50,
    ]);
    $formatter4 = PrettyFormatter::forChannel('cli', $limitedConfig);
    $renderer4 = new CliRenderer($formatter4);
    $request4 = new DumpRenderRequest($testData, 'cli');
    echo $renderer4->render($request4);
    echo "\n";

    echo "5. Theme switching:\n";
    $themes = ['light', 'dark'];
    foreach ($themes as $theme) {
        echo "Theme: $theme\n";
        $themeConfig = new FormatterConfiguration(['theme' => $theme]);
        $formatter = PrettyFormatter::forChannel('cli', $themeConfig);
        $renderer = new CliRenderer($formatter);
        $request = new DumpRenderRequest(['theme' => $theme, 'data' => 'Sample data'], 'cli');
        echo $renderer->render($request);
        echo "\n";
    }
}

function runRedactionExample(): void
{
    echo "\n🔒 Sensitive data redaction demo...\n\n";

    $configuration = new FormatterConfiguration([
        'redactionRules' => [
            ['pattern' => '/password/i', 'replacement' => '***'],
            ['pattern' => '/api[_-]?key/i', 'replacement' => '***'],
            ['pattern' => '/secret/i', 'replacement' => '***'],
            ['pattern' => '/email/i', 'replacement' => 'user@***.com'],
            ['pattern' => '/phone/i', 'replacement' => '***-***-****'],
        ],
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    $sensitiveData = [
        'user_info' => [
            'username' => 'john_doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1-555-123-4567',
        ],
        'auth_info' => [
            'password' => 'super-secret-password-123',
            'api_key' => 'sk-1234567890abcdef',
            'secret_token' => 'secret-token-for-auth',
        ],
        'system_config' => [
            'database_password' => 'db-pass-123',
            'email_password' => 'email-pass-456',
            'api_secret' => 'api-secret-key',
        ],
        'public_info' => [
            'name' => 'John Doe',
            'role' => 'Administrator',
            'status' => 'Active',
        ],
    ];

    echo "Raw data (contains sensitive information):\n";
    $request = new DumpRenderRequest($sensitiveData, 'cli');
    echo $renderer->render($request);
    echo "\n";

    echo "Note: Sensitive information has been automatically redacted!\n";
    echo "- Passwords replaced with ***\n";
    echo "- API keys replaced with ***\n";
    echo "- Email domains replaced with ***.com\n";
    echo "- Phone numbers replaced with ***-***-****\n";
}

function runThemeExample(): void
{
    echo "\n🎨 Theme switching demo...\n\n";

    $sampleData = [
        'theme' => 'demonstration',
        'colors' => ['red', 'green', 'blue', 'yellow'],
        'status' => true,
        'count' => 42,
        'metadata' => [
            'created' => date('Y-m-d H:i:s'),
            'version' => '2.0.0',
        ],
    ];

    $themes = [
        'light' => 'Light theme - suitable for bright environments',
        'dark' => 'Dark theme - suitable for dim environments',
    ];

    foreach ($themes as $theme => $description) {
        echo "$description:\n";
        $config = new FormatterConfiguration(['theme' => $theme]);
        $formatter = PrettyFormatter::forChannel('cli', $config);
        $renderer = new CliRenderer($formatter);
        $request = new DumpRenderRequest($sampleData, 'cli');
        echo $renderer->render($request);
        echo "\n";
    }
}

function runSqlExample(): void
{
    echo "\n📋 Running SQL auto-detection example...\n\n";
    echo "Starting external example file...\n";
    passthru('php ' . __DIR__ . '/cli/sql-highlight.php');
    echo "\n";
}

function runJsonExample(): void
{
    echo "\n📋 Running JSON detection example...\n\n";
    echo "Starting external example file...\n";
    passthru('php ' . __DIR__ . '/cli/json-detection.php');
    echo "\n";
}

function runDiffExample(): void
{
    echo "\n📋 Running diff comparison example...\n\n";
    echo "Starting external example file...\n";
    passthru('php ' . __DIR__ . '/cli/diff-comparison.php');
    echo "\n";
}

function runConditionalExample(): void
{
    echo "\n📋 Running conditional dump example...\n\n";
    echo "Starting external example file...\n";
    passthru('php ' . __DIR__ . '/cli/conditional-dump.php');
    echo "\n";
}

function runContextExample(): void
{
    echo "\n📋 Running context snapshot example...\n\n";
    echo "Starting external example file...\n";
    passthru('php ' . __DIR__ . '/cli/context-snapshot.php');
    echo "\n";
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function main(): void
{
    displayBanner();

    while (true) {
        $choice = displayMenu();

        switch ($choice) {
            case 1:
                runBasicExample();
                break;
            case 2:
                runExceptionExample();
                break;
            case 3:
                runPerformanceExample();
                break;
            case 4:
                runConfigurationExample();
                break;
            case 5:
                runRedactionExample();
                break;
            case 6:
                runThemeExample();
                break;
            case 7:
                runSqlExample();
                break;
            case 8:
                runJsonExample();
                break;
            case 9:
                runDiffExample();
                break;
            case 10:
                runConditionalExample();
                break;
            case 11:
                runContextExample();
                break;
            case 12:
                echo "\n👋 Thank you for using PrettyDumper Example Runner!\n";
                echo "For more examples, visit: examples/README.md\n\n";
                exit(0);
            default:
                echo "\n❌ Invalid choice, please enter 1-12\n\n";
                continue 2;
        }

        echo "\n" . str_repeat("-", 60) . "\n";
        $input = readline("Press Enter to continue, or type 'q' to exit: ");
        $continue = $input !== false ? trim($input) : '';
        if (strtolower($continue) === 'q') {
            break;
        }
        echo "\n";
    }

    echo "\n👋 Thank you for using PrettyDumper Example Runner!\n";
    echo "For more examples, visit: examples/README.md\n\n";
}

// 运行主程序
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php examples/run-examples.php\n";
}

/**
 * Usage instructions:
 *
 * 1. Make sure dependencies are installed:
 *    composer install
 *
 * 2. Run examples:
 *    php examples/run-examples.php
 *
 * 3. Follow prompts to select an example to run
 *
 * 4. View other examples:
 *    - examples/cli/ - Command line examples
 *    - examples/web/ - Web interface examples
 *    - examples/frameworks/ - Framework integration examples
 *
 * 5. Available examples:
 *    - CLI Basic Usage
 *    - Exception Handling
 *    - Performance Test
 *    - Configuration Options
 *    - Sensitive Data Redaction
 *    - Theme Switching
 *    - SQL Auto-Detection (NEW!)
 *    - JSON Detection (NEW!)
 *    - Diff Comparison (NEW!)
 *    - Conditional Dump (NEW!)
 *    - Context Snapshot (NEW!)
 */
