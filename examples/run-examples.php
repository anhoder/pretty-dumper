#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PrettyDumper 示例运行器
 *
 * 运行: php examples/run-examples.php
 *
 * 这个脚本提供了一个交互式的菜单来运行不同的示例
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
    echo "║                    PrettyDumper 示例运行器                  ║\n";
    echo "║                                                              ║\n";
    echo "║  强大的 PHP 调试工具 - 美化输出变量、异常和调用栈信息       ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

function displayMenu(): int
{
    echo "请选择要运行的示例：\n\n";
    echo "1. CLI 基本使用示例\n";
    echo "2. CLI 异常处理示例\n";
    echo "3. 性能测试示例\n";
    echo "4. 配置选项演示\n";
    echo "5. 敏感信息脱敏演示\n";
    echo "6. 主题切换演示\n";
    echo "7. 退出\n\n";

    $input = readline("请输入选项编号 (1-7): ");
    $choice = $input !== false ? trim($input) : '';
    return (int) $choice;
}

function runBasicExample(): void
{
    echo "\n🚀 运行 CLI 基本使用示例...\n\n";

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
    echo "\n⚡ 运行异常处理示例...\n\n";

    $configuration = new FormatterConfiguration([
        'expandExceptions' => true,
        'showContext' => true,
        'theme' => 'dark',
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    try {
        throw new RuntimeException('数据库连接失败', 500);
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
    echo "\n⚡ 运行性能测试示例...\n\n";

    $configuration = new FormatterConfiguration([
        'maxItems' => 100,
        'performanceThreshold' => 3000,
    ]);

    $formatter = PrettyFormatter::forChannel('cli', $configuration);
    $renderer = new CliRenderer($formatter);

    echo "生成测试数据...\n";
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    // 生成大量数据
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
    echo "数据生成完成，用时: " . number_format(($generateEndTime - $startTime) * 1000, 2) . "ms\n";
    echo "内存使用: " . formatBytes(memory_get_usage(true) - $startMemory) . "\n\n";

    echo "开始格式化...\n";
    $formatStartTime = microtime(true);

    $request = new DumpRenderRequest($largeArray, 'cli');
    $output = $renderer->render($request);

    $formatEndTime = microtime(true);
    $endMemory = memory_get_usage(true);

    echo "格式化完成！\n";
    echo "格式化用时: " . number_format(($formatEndTime - $formatStartTime) * 1000, 2) . "ms\n";
    echo "输出长度: " . number_format(strlen($output)) . " 字符\n";
    echo "总内存使用: " . formatBytes($endMemory - $startMemory) . "\n\n";

    echo "前1000字符的输出预览:\n";
    echo substr($output, 0, 1000) . "...\n";
}

function runConfigurationExample(): void
{
    echo "\n⚙️  配置选项演示（包括新的缩进和颜色配置）...\n\n";

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

    echo "1. 默认配置（4空格缩进）:\n";
    $defaultConfig = new FormatterConfiguration();
    $formatter1 = PrettyFormatter::forChannel('cli', $defaultConfig);
    $renderer1 = new CliRenderer($formatter1);
    $request1 = new DumpRenderRequest($testData, 'cli');
    echo $renderer1->render($request1);
    echo "\n";

    echo "2. 2空格缩进配置:\n";
    $smallIndentConfig = new FormatterConfiguration([
        'indentSize' => 2,
        'indentStyle' => 'spaces',
    ]);
    $formatter2 = PrettyFormatter::forChannel('cli', $smallIndentConfig);
    $renderer2 = new CliRenderer($formatter2);
    $request2 = new DumpRenderRequest($testData, 'cli');
    echo $renderer2->render($request2);
    echo "\n";

    echo "3. Tab缩进配置:\n";
    $tabIndentConfig = new FormatterConfiguration([
        'indentStyle' => 'tabs',
    ]);
    $formatter3 = PrettyFormatter::forChannel('cli', $tabIndentConfig);
    $renderer3 = new CliRenderer($formatter3);
    $request3 = new DumpRenderRequest($testData, 'cli');
    echo $renderer3->render($request3);
    echo "\n";

    echo "4. 限制深度和项目数:\n";
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

    echo "5. 主题切换:\n";
    $themes = ['light', 'dark'];
    foreach ($themes as $theme) {
        echo "主题: $theme\n";
        $themeConfig = new FormatterConfiguration(['theme' => $theme]);
        $formatter = PrettyFormatter::forChannel('cli', $themeConfig);
        $renderer = new CliRenderer($formatter);
        $request = new DumpRenderRequest(['theme' => $theme, 'data' => '示例数据'], 'cli');
        echo $renderer->render($request);
        echo "\n";
    }
}

function runRedactionExample(): void
{
    echo "\n🔒 敏感信息脱敏演示...\n\n";

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

    echo "原始数据（包含敏感信息）:\n";
    $request = new DumpRenderRequest($sensitiveData, 'cli');
    echo $renderer->render($request);
    echo "\n";

    echo "注意：敏感信息已被自动脱敏处理！\n";
    echo "- 密码被替换为 ***\n";
    echo "- API密钥被替换为 ***\n";
    echo "- 邮箱域名被替换为 ***.com\n";
    echo "- 手机号被替换为 ***-***-****\n";
}

function runThemeExample(): void
{
    echo "\n🎨 主题切换演示...\n\n";

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
        'light' => '亮色主题 - 适合明亮环境',
        'dark' => '暗色主题 - 适合昏暗环境',
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
                echo "\n👋 感谢使用 PrettyDumper 示例运行器！\n";
                echo "查看更多示例请访问: examples/README.md\n\n";
                exit(0);
            default:
                echo "\n❌ 无效的选择，请输入 1-7\n\n";
                continue 2;
        }

        echo "\n" . str_repeat("-", 60) . "\n";
        $input = readline("按回车继续，或输入 'q' 退出: ");
        $continue = $input !== false ? trim($input) : '';
        if (strtolower($continue) === 'q') {
            break;
        }
        echo "\n";
    }

    echo "\n👋 感谢使用 PrettyDumper 示例运行器！\n";
    echo "查看更多示例请访问: examples/README.md\n\n";
}

// 运行主程序
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "此脚本需要在命令行中运行。\n";
    echo "使用方法: php examples/run-examples.php\n";
}

/**
 * 使用说明：
 *
 * 1. 确保已经安装依赖：
 *    composer install
 *
 * 2. 运行示例：
 *    php examples/run-examples.php
 *
 * 3. 按照提示选择要运行的示例
 *
 * 4. 查看其他示例：
 *    - examples/cli/ - 命令行示例
 *    - examples/web/ - Web界面示例
 *    - examples/frameworks/ - 框架集成示例
 */
