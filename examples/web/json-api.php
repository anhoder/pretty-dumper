<?php

declare(strict_types=1);

/**
 * PrettyDumper JSON API Data Processing Example
 *
 * Run: php -S localhost:8081 examples/web/json-api.php
 * Then visit: http://localhost:8081
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\WebRenderer;

// Simulate API response data
$apiResponses = [
    'users' => [
        'status' => 'success',
        'data' => [
            [
                'id' => 1,
                'name' => 'Alice Zhang',
                'email' => 'alice@example.com',
                'profile' => [
                    'avatar' => 'https://example.com/avatar1.jpg',
                    'bio' => 'Full Stack Developer',
                    'skills' => ['PHP', 'JavaScript', 'Python', 'Go'],
                ],
                'created_at' => '2024-01-15T10:30:00Z',
                'updated_at' => '2024-03-20T14:45:00Z',
            ],
            [
                'id' => 2,
                'name' => 'Bob Lee',
                'email' => 'bob@example.com',
                'profile' => [
                    'avatar' => 'https://example.com/avatar2.jpg',
                    'bio' => 'UI/UX Designer',
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
                'description' => '14-inch MacBook Pro, M3 chip',
                'price' => 15999.00,
                'currency' => 'CNY',
                'categories' => ['Electronics', 'Computers', 'Laptops'],
                'specifications' => [
                    'processor' => 'Apple M3',
                    'memory' => '16GB',
                    'storage' => '512GB SSD',
                    'display' => '14.2-inch Liquid Retina XDR',
                ],
                'in_stock' => true,
                'rating' => 4.8,
                'reviews_count' => 156,
            ],
            [
                'id' => 102,
                'name' => 'iPhone 15 Pro',
                'description' => '6.1-inch iPhone 15 Pro, A17 Pro chip',
                'price' => 8999.00,
                'currency' => 'CNY',
                'categories' => ['Electronics', 'Mobile Phones'],
                'specifications' => [
                    'processor' => 'A17 Pro',
                    'memory' => '8GB',
                    'storage' => '256GB',
                    'display' => '6.1-inch Super Retina XDR',
                ],
                'in_stock' => false,
                'rating' => 4.6,
                'reviews_count' => 89,
            ],
        ],
    ],

    'error' => [
        'status' => 'error',
        'message' => 'Invalid request parameters',
        'errors' => [
            [
                'field' => 'email',
                'message' => 'Invalid email format',
                'code' => 'INVALID_EMAIL',
            ],
            [
                'field' => 'password',
                'message' => 'Password must be at least 8 characters',
                'code' => 'PASSWORD_TOO_SHORT',
            ],
        ],
        'timestamp' => date('c'),
        'request_id' => uniqid('req_'),
    ],
];

// Configuration
$configuration = new FormatterConfiguration([
    'maxDepth' => 6,
    'maxItems' => 100,
    'showContext' => true,
]);

$formatter = PrettyFormatter::forChannel('web', $configuration);
$themes = \Anhoder\PrettyDumper\Support\ThemeRegistry::withDefaults();
$renderer = new WebRenderer($formatter, $themes);

// Get demo type
$demo = $_GET['demo'] ?? 'users';
$responseData = $apiResponses[$demo] ?? $apiResponses['users'];

// Add additional debug information
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrettyDumper JSON API Example</title>
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
        <h1>PrettyDumper JSON API Example</h1>
        <p>Demonstrates how to format and display JSON API response data using PrettyDumper</p>

        <div class="nav">
            <a href="?demo=users" class="?php echo $demo === 'users' ? 'active' : ''; ?">User Data</a>
            <a href="?demo=products" class="?php echo $demo === 'products' ? 'active' : ''; ?">Product Data</a>
            <a href="?demo=error" class="?php echo $demo === 'error' ? 'active' : ''; ?">Error Response</a>
        </div>

        <?php
        switch ($demo) {
            case 'users':
                echo '<h2>User Data API Response</h2>';
                echo '<div class="api-info">';
                echo '<strong>API Endpoint:</strong> GET /api/users<br>';
                echo '<strong>Status:</strong> <span class="status-success">200 OK</span><br>';
                echo '<strong>Description:</strong> Get user list with profiles and skills';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>Nested Data Structure</h4>';
                echo '<p>Display user information and nested profile data</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Multi-language Support</h4>';
                echo '<p>Proper display of names and descriptions</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Time Format</h4>';
                echo '<p>ISO 8601 standard time format parsing</p>';
                echo '</div>';
                echo '</div>';
                break;

            case 'products':
                echo '<h2>Product Data API Response</h2>';
                echo '<div class="api-info">';
                echo '<strong>API Endpoint:</strong> GET /api/products<br>';
                echo '<strong>Status:</strong> <span class="status-success">200 OK</span><br>';
                echo '<strong>Description:</strong> Get product catalog with detailed specifications and stock status';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>Complex Specification Data</h4>';
                echo '<p>Nested specification parameters and technical details</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Price and Currency</h4>';
                echo '<p>Clear display of currency format and price information</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Stock Status</h4>';
                echo '<p>Visual representation of boolean values and ratings</p>';
                echo '</div>';
                echo '</div>';
                break;

            case 'error':
                echo '<h2>Error Response API Example</h2>';
                echo '<div class="api-info">';
                echo '<strong>API Endpoint:</strong> POST /api/users<br>';
                echo '<strong>Status:</strong> <span class="status-error">400 Bad Request</span><br>';
                echo '<strong>Description:</strong> Form validation failed, returns detailed error information';
                echo '</div>';

                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h4>Error Field Mapping</h4>';
                echo '<p>Detailed field-level error information and codes</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Timestamp and Tracking</h4>';
                echo '<p>Error occurrence time and request ID for debugging</p>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h4>Multi-language Errors</h4>';
                echo '<p>Clear display of error messages</p>';
                echo '</div>';
                echo '</div>';
                break;
        }
        ?>

        <div class="demo-section">
            <h3>Formatted API Response Data</h3>
            <?php
            $request = new DumpRenderRequest($debugInfo, 'web');
            echo $renderer->render($request);
            ?>
        </div>

        <div class="demo-section">
            <h3>Debug Information</h3>
            <p>Debug data including request information and memory usage:</p>
            <ul>
                <li><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD'] ?? 'GET'; ?></li>
                <li><strong>Request URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></li>
                <li><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                <li><strong>Memory Usage:</strong> <?php echo formatBytes(memory_get_usage(true)); ?></li>
            </ul>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Startup instructions:
 *
 * 1. Run in terminal:
 *    php -S localhost:8081 examples/web/json-api.php
 *
 * 2. Visit in browser:
 *    http://localhost:8081
 *    http://localhost:8081?demo=users
 *    http://localhost:8081?demo=products
 *    http://localhost:8081?demo=error
 *
 * 3. Features:
 *    - Multi-language content support
 *    - Handle complex nested JSON structures
 *    - Timestamp and date formatting
 *    - Clear display of prices and numbers
 *    - Visualization of boolean values and statuses
 *    - Memory usage monitoring
 *    - Responsive web interface
 */
