<?php

declare(strict_types=1);

/**
 * PrettyDumper Web 基本使用示例
 *
 * 运行: php -S localhost:8080 examples/web/basic-web.php
 * 然后访问: http://localhost:8080
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\WebRenderer;

// 配置 Web 渲染器 - 包含新的颜色和缩进配置
$configuration = new FormatterConfiguration([
    'maxDepth' => 5,
    'showContext' => true,
    'indentSize' => 2,        // 2空格缩进，更紧凑
    'indentStyle' => 'spaces', // 使用空格缩进
]);

$formatter = PrettyFormatter::forChannel('web', $configuration);
$themes = \PrettyDumper\Support\ThemeRegistry::withDefaults();
$renderer = new WebRenderer($formatter, $themes);

$themeParam = $_GET['theme'] ?? 'auto';
$allowedThemes = ['auto', 'light', 'dark'];
if (!in_array($themeParam, $allowedThemes, true)) {
    $themeParam = 'auto';
}

$tableMetaParamRaw = $_GET['table_meta'] ?? null;
$tableMetaEnabled = false;
if ($tableMetaParamRaw !== null) {
    $filteredTableMeta = filter_var($tableMetaParamRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($filteredTableMeta !== null) {
        $tableMetaEnabled = $filteredTableMeta;
    } else {
        $tableMetaEnabled = ((int) $tableMetaParamRaw) === 1;
    }
}

// 路由处理
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrettyDumper Web 示例</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        .nav {
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .nav a {
            margin-right: 15px;
            padding: 8px 16px;
            text-decoration: none;
            background: #007bff;
            color: white;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav a:hover {
            background: #0056b3;
        }
        .demo-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
        }
        .code-example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .options {
            margin: 12px 0 24px;
            font-size: 14px;
            color: #555;
        }
        .options a {
            color: #007bff;
            text-decoration: none;
            margin-left: 6px;
        }
        .options a:hover {
            text-decoration: underline;
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .theme-toggle:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">切换主题</button>

    <div class="container">
        <h1>PrettyDumper Web 示例</h1>

        <div class="nav">
            <?php
            $themeQuery = $themeParam !== 'auto' ? '&theme=' . urlencode($themeParam) : '';
            $tableMetaQuery = $tableMetaParamRaw !== null ? '&table_meta=' . ($tableMetaEnabled ? '1' : '0') : '';
            $extraQuery = $themeQuery . $tableMetaQuery;
            ?>
            <a href="?demo=simple<?php echo $extraQuery; ?>">简单数组</a>
            <a href="?demo=nested<?php echo $extraQuery; ?>">嵌套数据</a>
            <a href="?demo=object<?php echo $extraQuery; ?>">对象示例</a>
            <a href="?demo=exception<?php echo $extraQuery; ?>">异常处理</a>
        </div>

        <?php
        $demo = $_GET['demo'] ?? 'simple';

        $renderDump = function (mixed $payload) use ($renderer, $themeParam, $tableMetaEnabled) {
            $request = new DumpRenderRequest($payload, 'web', [
                'theme' => $themeParam,
                'showTableVariableMeta' => $tableMetaEnabled,
            ]);
            echo $renderer->render($request);
        };

        $themeSuffix = $themeParam !== 'auto' ? '&theme=' . urlencode($themeParam) : '';
        $currentDemo = $demo;
        $toggleMetaSuffix = '&table_meta=' . ($tableMetaEnabled ? '0' : '1');
        $toggleMetaLabel = $tableMetaEnabled ? '关闭变量名展示' : '开启变量名展示';
        ?>

        <p class="options">
            子级变量名展示：<strong><?php echo $tableMetaEnabled ? '开启' : '关闭'; ?></strong>
            <a href="?demo=<?php echo urlencode($currentDemo) . $themeSuffix . $toggleMetaSuffix; ?>"><?php echo $toggleMetaLabel; ?></a>
        </p>

        <?php
        switch ($demo) {
            case 'simple':
                echo '<h2>简单数组示例</h2>';
                echo '<div class="code-example">$data = ["name" => "John", "age" => 30, "city" => "New York"];</div>';

                $simpleData = [
                    'name' => 'John Doe',
                    'age' => 30,
                    'email' => 'john@example.com',
                    'active' => true,
                    'balance' => 1234.56,
                ];
                $renderDump($simpleData);
                break;

            case 'nested':
                echo '<h2>嵌套数据结构</h2>';
                echo '<div class="code-example">包含多层嵌套的数组和对象</div>';

                $nestedData = [
                    'company' => [
                        'name' => 'Tech Corp',
                        'founded' => 2020,
                        'employees' => [
                            ['name' => 'Alice', 'role' => 'Developer', 'skills' => ['PHP', 'JavaScript', 'Python']],
                            ['name' => 'Bob', 'role' => 'Designer', 'skills' => ['Figma', 'Photoshop', 'Illustrator']],
                            ['name' => 'Charlie', 'role' => 'Manager', 'skills' => ['Leadership', 'Communication']],
                        ],
                    ],
                    'projects' => [
                        ['id' => 1, 'name' => 'Website Redesign', 'status' => 'completed'],
                        ['id' => 2, 'name' => 'Mobile App', 'status' => 'in_progress'],
                        ['id' => 3, 'name' => 'API Development', 'status' => 'planned'],
                    ],
                ];
                $renderDump($nestedData);
                break;

            case 'object':
                echo '<h2>对象和类实例</h2>';
                echo '<div class="code-example">自定义类和对象实例</div>';

                class Product {
                    public function __construct(
                        public string $name,
                        public float $price,
                        public array $categories,
                        public ?DateTime $createdAt = null
                    ) {
                        $this->createdAt = $createdAt ?? new DateTime();
                    }
                }

                $products = [
                    new Product('Laptop', 999.99, ['Electronics', 'Computers']),
                    new Product('Mouse', 29.99, ['Electronics', 'Accessories']),
                    new Product('Keyboard', 79.99, ['Electronics', 'Accessories']),
                ];

                $renderDump($products);
                break;

            case 'exception':
                echo '<h2>异常信息展示</h2>';
                echo '<div class="code-example">异常链和上下文信息</div>';

                try {
                    throw new RuntimeException('数据库查询失败', 500);
                } catch (Exception $e) {
                    $exceptionData = [
                        'error' => $e,
                        'context' => [
                            'query' => 'SELECT * FROM users WHERE id = ?',
                            'params' => [123],
                            'timestamp' => date('Y-m-d H:i:s'),
                            'server' => $_SERVER['SERVER_NAME'] ?? 'localhost',
                        ],
                        'debug' => [
                            'memory_usage' => memory_get_usage(true),
                            'peak_memory' => memory_get_peak_usage(true),
                            'php_version' => PHP_VERSION,
                        ],
                    ];

                    $renderDump($exceptionData);
                }
                break;

            default:
                echo '<h2>欢迎使用 PrettyDumper Web 示例</h2>';
                echo '<p>点击上面的链接查看不同的示例。</p>';
                echo '<p><strong>特性包括：</strong></p>';
                echo '<ul>';
                echo '<li>响应式设计</li>';
                echo '<li>主题切换（亮色/暗色）</li>';
                echo '<li>可折叠的嵌套结构</li>';
                echo '<li>类型信息和元数据</li>';
                echo '<li>截断和性能优化</li>';
                echo '</ul>';
        }
        ?>
    </div>

    <script>
        function toggleTheme() {
            const url = new URL(window.location.href);
            const current = url.searchParams.get('theme') || '<?php echo $themeParam; ?>' || 'auto';
            let next;
            if (current === 'dark') {
                next = 'light';
            } else {
                next = 'dark';
            }
            url.searchParams.set('theme', next);
            window.location.href = url.toString();
        }

        (function initialiseTheme() {
            const paramTheme = '<?php echo $themeParam; ?>';
            if (paramTheme === 'auto') {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                return;
            }

            document.documentElement.setAttribute('data-theme', paramTheme);
        }());
    </script>
</body>
</html>
