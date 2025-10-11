<?php

declare(strict_types=1);

/**
 * PrettyDumper CLI 基本使用示例
 *
 * 运行: php examples/cli/basic-usage.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\CliRenderer;

echo "=== PrettyDumper CLI 基本示例 ===\n\n";

// 基本配置
$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'maxItems' => 50,
    'showContext' => true,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter);

// 示例1: 简单数组
echo "1. 简单数组:\n";
$simpleArray = ['name' => 'John', 'age' => 30, 'city' => 'New York'];
$request = new DumpRenderRequest($simpleArray, 'cli');
echo $renderer->render($request);
echo "\n";

// 示例2: 嵌套数据结构
echo "2. 嵌套数据结构:\n";
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

// 示例3: 对象和类实例
echo "3. 对象和类实例:\n";
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

// 示例4: 大数据结构（截断演示）
echo "4. 大数据结构（截断演示）:\n";
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

echo "=== 示例完成 ===\n";