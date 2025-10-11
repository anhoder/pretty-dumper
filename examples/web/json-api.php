<?php

declare(strict_types=1);

/**
 * PrettyDumper JSON API 数据处理示例
 *
 * 运行: php -S localhost:8081 examples/web/json-api.php
 * 然后访问: http://localhost:8081
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\WebRenderer;

// 模拟 API 响应数据
$apiResponses = [
    'users' => [
        'status' => 'success',
        'data' => [
            [
                'id' => 1,
                'name' => '张三',
                'email' => 'zhangsan@example.com',
                'profile' => [
                    'avatar' => 'https://example.com/avatar1.jpg',
                    'bio' => '全栈开发工程师',
                    'skills' => ['PHP', 'JavaScript', 'Python', 'Go'],
                ],
                'created_at' => '2024-01-15T10:30:00Z',
                'updated_at' => '2024-03-20T14:45:00Z',
            ],
            [
                'id' => 2,
                'name' => '李四',
                'email' => 'lisi@example.com',
                'profile' => [
                    'avatar' => 'https://example.com/avatar2.jpg',
                    'bio' => 'UI/UX 设计师',
                    'skills' => ['Figma', 'Sketch', 'Photoshop', 'Illustrator'],
                ],
                'created_at' => '2024-02-01T09:15:00Z',
                'updated_at' => '2024-03-18T16:20:00Z',
            ],
        ],
        'pagination' => [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 2,
            'last_page' => 1,
        ],
    ],

    'products' => [
        'status' => 'success',
        'data' => [
            [
                'id' => 101,
                'name' => 'MacBook Pro',
                'description' => '14英寸 MacBook Pro，M3芯片',
                'price' => 15999.00,
                'currency' => 'CNY',
                'categories' => ['电子产品', '电脑', '笔记本'],
                'specifications' => [
                    'processor' => 'Apple M3',
                    'memory' => '16GB',
                    'storage' => '512GB SSD',
                    'display' => '14.2英寸 Liquid Retina XDR',
                ],
                'in_stock' => true,
                'rating' => 4.8,
                'reviews_count' => 156,
            ],
            [
                'id' => 102,
                'name' => 'iPhone 15 Pro',
                'description' => '6.1英寸 iPhone 15 Pro，A17 Pro芯片',
                'price' => 8999.00,
                'currency' => 'CNY',
                'categories' => ['电子产品', '手机'],
                'specifications' => [
                    'processor' => 'A17 Pro',
                    'memory' => '8GB',
                    'storage' => '256GB',
                    'display' => '6.1英寸 Super Retina XDR',
                ],
                'in_stock' => false,
                'rating' => 4.6,
                'reviews_count' => 89,
            ],
        ],
    ],

    'error' => [
        'status' => 'error',
        'message' => '请求参数无效',
        'errors' => [
            [
                'field' => 'email',
                'message' => '邮箱格式不正确',
                'code' => 'INVALID_EMAIL',
            ],
            [
                'field' => 'password',
                'message' => '密码长度至少8位',
                'code' => 'PASSWORD_TOO_SHORT',
            ],
        ],
        'timestamp' => date('c'),
        'request_id' => uniqid('req_'),
    ],
];

// 配置
$configuration = new FormatterConfiguration([
    'maxDepth' => 6,
    'maxItems' => 100,
    'showContext' => true,
]);

$formatter = PrettyFormatter::forChannel('web', $configuration);
$themes = \PrettyDumper\Support\ThemeRegistry::withDefaults();
$renderer = new WebRenderer($formatter, $themes);

// 获取演示类型
$demo = $_GET['demo'] ?? 'users';
$responseData = $apiResponses[$demo] ?? $apiResponses['users'];

// 添加额外的调试信息
$debugInfo = [
    'api_response' => $responseData,
    'debug' => [
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '/',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'timestamp' => date('Y-m-d H:i:s'),
        'memory_usage' => [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'formatted' => formatBytes(memory_get_usage(true)),
        ],
    ],
];

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrettyDumper JSON API 示例</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .nav {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav a {
            padding: 10px 20px;
            text-decoration: none;
            background: #007bff;
            color: white;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .nav a:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .nav a.active {
            background: #28a745;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        .demo-section {
            margin: 30px 0;
            padding: 25px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #fafafa;
        }
        .api-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .api-info strong {
            color: #1976d2;
        }
        .endpoint {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
        }
        .status-success {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .feature-card h4 {
            margin-top: 0;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PrettyDumper JSON API 示例</h1>
        <p>展示如何使用 PrettyDumper 格式化和显示 JSON API 响应数据</p>

        <div class="nav">
            <a href="?demo=users" class="?php echo $demo === 'users' ? 'active' : ''; ?">用户数据</a>
            <a href="?demo=products" class="?php echo $demo === 'products' ? 'active' : ''; ?">产品数据</a>
            <a href="?demo=error" class="?php echo $demo === 'error' ? 'active' : ''; ?">错误响应</a>
        </div>

        <?php
        switch ($demo) {
            case 'users':
                echo '<h2>用户数据 API 响应</h2>';
                echo '<div class="api-info">';
                echo '<strong>API 端点：</strong> GET /api/users<br>';
                echo '<strong>状态：</strong> <span class="status-success">200 OK</span><br>';
                echo '<strong>描述：</strong> 获取用户列表，包含个人资料和技能信息';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>嵌套数据结构</h4>';
                echo '<p>展示用户信息和嵌套的个人资料数据</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>多语言支持</h4>';
                echo '<p>中文姓名和描述的正确显示</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>时间格式</h4>';
                echo '<p>ISO 8601 标准时间格式解析</p>';
                echo '</div>';
                echo '</div>';
                break;

            case 'products':
                echo '<h2>产品数据 API 响应</h2>';
                echo '<div class="api-info">';
                echo '<strong>API 端点：</strong> GET /api/products<br>';
                echo '<strong>状态：</strong> <span class="status-success">200 OK</span><br>';
                echo '<strong>描述：</strong> 获取产品目录，包含详细规格和库存状态';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>复杂规格数据</h4>';
                echo '<p>嵌套的规格参数和技术细节</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>价格和货币</h4>';
                echo '<p>货币格式和价格信息的清晰展示</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>库存状态</h4>';
                echo '<p>布尔值和评分的可视化表示</p>';
                echo '</div>';
                echo '</div>';
                break;

            case 'error':
                echo '<h2>错误响应 API 示例</h2>';
                echo '<div class="api-info">';
                echo '<strong>API 端点：</strong> POST /api/users<br>';
                echo '<strong>状态：</strong> <span class="status-error">400 Bad Request</span><br>';
                echo '<strong>描述：</strong> 表单验证失败，返回详细的错误信息';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>错误字段映射</h4>';
                echo '<p>详细的字段级别错误信息和代码</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>时间戳和追踪</h4>';
                echo '<p>错误发生时间和请求ID用于调试</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>多语言错误</h4>';
                echo '<p>中文错误信息的清晰展示</p>';
                echo '</div>';
                echo '</div>';
                break;
        }
        ?>

        <div class="demo-section">
            <h3>格式化的 API 响应数据</h3>
            <?php
            $request = new DumpRenderRequest($debugInfo, 'web');
            echo $renderer->render($request);
            ?>
        </div>

        <div class="demo-section">
            <h3>调试信息</h3>
            <p>包含请求信息和内存使用情况的调试数据：</p>
            <ul>
                <li><strong>请求方法：</strong> <?php echo $_SERVER['REQUEST_METHOD'] ?? 'GET'; ?></li>
                <li><strong>请求URI：</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></li>
                <li><strong>当前时间：</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                <li><strong>内存使用：</strong> <?php echo formatBytes(memory_get_usage(true)); ?></li>
            </ul>
        </div>
    </div>
</body>
</html>

<?php
/**
 * 启动说明：
 *
 * 1. 在终端中运行：
 *    php -S localhost:8081 examples/web/json-api.php
 *
 * 2. 在浏览器中访问：
 *    http://localhost:8081
 *    http://localhost:8081?demo=users
 *    http://localhost:8081?demo=products
 *    http://localhost:8081?demo=error
 *
 * 3. 特性说明：
 *    - 支持中文内容显示
 *    - 处理复杂的嵌套JSON结构
 *    - 时间戳和日期格式化
 *    - 价格和数值的清晰展示
 *    - 布尔值和状态的可视化
 *    - 内存使用监控
 *    - 响应式Web界面
 */
