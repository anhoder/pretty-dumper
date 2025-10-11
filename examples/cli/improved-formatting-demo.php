#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 改进后的格式化功能演示
 *
 * 展示新的缩进配置、颜色区分和视觉改进
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper 改进格式化功能演示\n";
echo str_repeat("=", 60) . "\n\n";

// 示例数据
$sampleData = [
    'company' => [
        'name' => 'TechCorp',
        'employees' => [
            [
                'id' => 1,
                'name' => '张三',
                'position' => 'Senior Developer',
                'skills' => ['PHP', 'Laravel', 'Vue.js', 'MySQL'],
                'active' => true,
                'salary' => 15000.50,
                'join_date' => '2023-01-15',
            ],
            [
                'id' => 2,
                'name' => '李四',
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

// 演示1: 默认配置（4空格缩进）
echo "📋 演示1: 默认配置（4空格缩进）\n";
echo str_repeat("-", 40) . "\n";

$defaultConfig = new FormatterConfiguration();
$formatter1 = PrettyFormatter::forChannel('cli', $defaultConfig);
$renderer1 = new CliRenderer($formatter1);
$request1 = new DumpRenderRequest($sampleData, 'cli');
echo $renderer1->render($request1);
echo "\n\n";

// 演示2: 2空格缩进 - 更紧凑
echo "📋 演示2: 2空格缩进（更紧凑）\n";
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

// 演示3: Tab缩进
echo "📋 演示3: Tab缩进\n";
echo str_repeat("-", 40) . "\n";

$tabConfig = new FormatterConfiguration([
    'indentStyle' => 'tabs',
]);
$formatter3 = PrettyFormatter::forChannel('cli', $tabConfig);
$renderer3 = new CliRenderer($formatter3);
$request3 = new DumpRenderRequest($sampleData, 'cli');
echo $renderer3->render($request3);
echo "\n\n";

// 演示4: 不同类型数据的颜色区分
echo "📋 演示4: 各种数据类型的颜色区分\n";
echo str_repeat("-", 40) . "\n";

$typeDemoData = [
    '字符串' => 'Hello World',
    '整数' => 42,
    '浮点数' => 3.14159,
    '布尔值真' => true,
    '布尔值假' => false,
    '空值' => null,
    '数组' => [1, 2, 3, 4, 5],
    '对象' => new stdClass(),
    '嵌套结构' => [
        'level1' => [
            'level2' => [
                'level3' => '深层值',
            ],
        ],
    ],
];

$formatter4 = PrettyFormatter::forChannel('cli', $defaultConfig);
$renderer4 = new CliRenderer($formatter4);
$request4 = new DumpRenderRequest($typeDemoData, 'cli');
echo $renderer4->render($request4);
echo "\n\n";

// 演示5: 复杂嵌套结构
echo "📋 演示5: 复杂嵌套结构展示\n";
echo str_repeat("-", 40) . "\n";

$complexData = [
    'database' => [
        'connections' => [
            'mysql' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'myapp',
                'username' => 'root',
                'password' => 'secret123', // 这将被脱敏
                'options' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'strict' => true,
                ],
            ],
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => 'redis-pass', // 这将被脱敏
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
echo "✅ 改进格式化功能演示完成！\n\n";

echo "🎯 主要改进总结：\n";
echo "1. ✅ 可配置缩进（空格数、Tab/空格选择）\n";
echo "2. ✅ 增强的视觉区分（▶/▼ 图标，树形连接线 ├── ）\n";
echo "3. ✅ 类型专用颜色方案：\n";
echo "   - 🟢 字符串：绿色\n";
echo "   - 🟣 数字：洋红色\n";
echo "   - 🔵 布尔值：蓝色\n";
echo "   - ⚫ 空值：灰色\n";
echo "   - 🟠 数组：青色\n";
echo "   - 🟣 对象：亮洋红色\n";
echo "4. ✅ 保持高性能和向后兼容性\n";
echo "5. ✅ 支持敏感信息自动脱敏\n\n";

echo "💡 使用建议：\n";
echo "- 小屏幕或紧凑输出：使用2空格缩进\n";
echo "- 标准开发环境：使用默认4空格缩进\n";
echo "- 深度嵌套数据：考虑使用Tab缩进\n";
echo "- 生产环境日志：配合脱敏规则使用\n";
