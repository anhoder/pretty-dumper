<?php

declare(strict_types=1);

/**
 * PrettyDumper Web Basic Usage Example
 *
 * Run: php -S localhost:8080 examples/web/basic-web.php
 * Then visit: http://localhost:8080
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\WebRenderer;

// Configure Web renderer - includes new color and indent settings
$configuration = new FormatterConfiguration([
    'maxDepth' => 5,
    'showContext' => true,
    'indentSize' => 2,        // 2-space indent, more compact
    'indentStyle' => 'spaces', // Use space indentation
]);

$formatter = PrettyFormatter::forChannel('web', $configuration);
$themes = \Anhoder\PrettyDumper\Support\ThemeRegistry::withDefaults();
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

// Route handling
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrettyDumper Web Example</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>PrettyDumper Web Example</h1>

        <div class="nav">
            <?php
            $themeQuery = $themeParam !== 'auto' ? '&theme=' . urlencode($themeParam) : '';
            $tableMetaQuery = $tableMetaParamRaw !== null ? '&table_meta=' . ($tableMetaEnabled ? '1' : '0') : '';
            $extraQuery = $themeQuery . $tableMetaQuery;
            ?>
            <a href="?demo=simple<?php echo $extraQuery; ?>">Simple Array</a>
            <a href="?demo=nested<?php echo $extraQuery; ?>">Nested Data</a>
            <a href="?demo=object<?php echo $extraQuery; ?>">Object Example</a>
            <a href="?demo=exception<?php echo $extraQuery; ?>">Exception Handling</a>
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
        ?>

        <p class="options">
            Child variable names display: <strong><?php echo $tableMetaEnabled ? 'Enabled' : 'Disabled'; ?></strong>
            <a href="?demo=<?php echo urlencode($currentDemo) . $themeSuffix . $toggleMetaSuffix; ?>"><?php echo $tableMetaEnabled ? 'Disable' : 'Enable'; ?></a>
        </p>

        <?php
        switch ($demo) {
            case 'simple':
                echo '<h2>Simple Array Example</h2>';
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
                echo '<h2>Nested Data Structure</h2>';
                echo '<div class="code-example">Array and object with multiple nesting levels</div>';

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
                echo '<h2>Objects and Class Instances</h2>';
                echo '<div class="code-example">Custom classes and object instances</div>';

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
                echo '<h2>Exception Information Display</h2>';
                echo '<div class="code-example">Exception chain and context information</div>';

                try {
                    throw new RuntimeException('Database query failed', 500);
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
                echo '<h2>Welcome to PrettyDumper Web Example</h2>';
                echo '<p>Click the links above to view different examples.</p>';
                echo '<p><strong>Features include:</strong></p>';
                echo '<ul>';
                echo '<li>Responsive design</li>';
                echo '<li>Theme switching (light/dark)</li>';
                echo '<li>Collapsible nested structures</li>';
                echo '<li>Type information and metadata</li>';
                echo '<li>Truncation and performance optimization</li>';
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
