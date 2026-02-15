<?php

declare(strict_types=1);

/**
 * PrettyDumper Web SQL Highlight Example
 *
 * Run: php -S localhost:8082 -t examples/web/sql-highlight.php
 * Then visit: http://localhost:8082
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\WebRenderer;

$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'showContext' => false,
]);

$formatter = PrettyFormatter::forChannel('web', $configuration);
$themes = \Anhoder\PrettyDumper\Support\ThemeRegistry::withDefaults();
$renderer = new WebRenderer($formatter, $themes);

$themeParam = $_GET['theme'] ?? 'auto';
$allowedThemes = ['auto', 'light', 'dark'];
if (!in_array($themeParam, $allowedThemes, true)) {
    $themeParam = 'auto';
}

$demo = $_GET['demo'] ?? 'simple';

$queries = [
    'simple' => "SELECT u.id, u.name, u.email FROM users u WHERE u.active = 1 LIMIT 10",
    'complex' => "SELECT 
        u.id,
        u.name,
        o.id as order_id,
        o.total,
        o.created_at
    FROM users u
    INNER JOIN orders o ON u.id = o.user_id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE u.status = 'active'
      AND o.created_at >= '2024-01-01'
    ORDER BY o.created_at DESC
    LIMIT 50",
    'insert' => "INSERT INTO products (name, description, price, category, in_stock, created_at) VALUES ('New Product', 'Product description', 99.99, 'Electronics', 1, NOW())",
    'update' => "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = 123 AND active = 1",
    'delete' => "DELETE FROM sessions WHERE expired_at < NOW() OR user_id IS NULL",
    'aggregate' => "SELECT 
        u.department,
        COUNT(*) as total_users,
        AVG(u.salary) as avg_salary,
        MAX(u.salary) as max_salary,
        MIN(u.salary) as min_salary
    FROM users u
    WHERE u.hired_date >= '2020-01-01'
    GROUP BY u.department
    HAVING COUNT(*) > 5
    ORDER BY avg_salary DESC",
    'cte' => "WITH 
    monthly_sales AS (
        SELECT 
            DATE_FORMAT(sale_date, '%Y-%m') as month,
            product_id,
            SUM(amount) as total_sales
        FROM sales
        WHERE sale_date >= '2024-01-01'
        GROUP BY DATE_FORMAT(sale_date, '%Y-%m'), product_id
    ),
    top_products AS (
        SELECT 
            month,
            product_id,
            total_sales,
            RANK() OVER (PARTITION BY month ORDER BY total_sales DESC) as rank
        FROM monthly_sales
    )
    SELECT 
        ms.month,
        p.name as product_name,
        tp.total_sales
    FROM top_products tp
    JOIN products p ON tp.product_id = p.id
    WHERE tp.rank <= 5
    ORDER BY ms.month, tp.total_sales DESC",
];

$query = $queries[$demo] ?? $queries['simple'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrettyDumper Web SQL Example</title>
    <style>
        :root {
            --example-bg: #f5f5f5;
            --example-text: #333;
            --example-border: #e9ecef;
        }

        [data-theme="dark"] {
            --example-bg: #1a1a2e;
            --example-text: #e0e0e0;
            --example-border: #2d3748;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body > .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--example-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            color: var(--example-text);
        }

        body > .container h1,
        body > .container h2 {
            color: var(--example-text);
        }

        body > .container .nav {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        body > .container .nav a {
            padding: 10px 20px;
            text-decoration: none;
            background: #007bff;
            color: white;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        body > .container .nav a:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        body > .container .nav a.active {
            background: #28a745;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        body > .container .demo-section {
            margin: 30px 0;
            padding: 25px;
            border: 1px solid var(--example-border);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
        }

        body > .container .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }

        body > .container .info-box strong {
            color: #0c5460;
        }

        body > .container .endpoint {
            background: rgba(0, 0, 0, 0.05);
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
        }

        body > .container .status-success {
            color: #28a745;
            font-weight: bold;
        }

        body > .container .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        body > .container .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        body > .container .feature-card {
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
        }

        body > .container .feature-card h4 {
            margin-top: 0;
            color: #007bff;
        }

        body > .container .code-example {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 PrettyDumper Web SQL Example</h1>
        <p>Demonstrates automatic SQL detection and beautification in web environment</p>

        <div class="nav">
            <?php
            $themeQuery = $themeParam !== 'auto' ? '&theme=' . urlencode($themeParam) : '';
            $navItems = [
                'simple' => 'Simple SELECT',
                'complex' => 'Complex JOIN',
                'insert' => 'INSERT',
                'update' => 'UPDATE',
                'delete' => 'DELETE',
                'aggregate' => 'Aggregation',
                'cte' => 'CTE',
            ];
            foreach ($navItems as $key => $label) {
                $activeClass = $demo === $key ? 'active' : '';
                echo "<a href=\"?demo=$key$themeQuery\" class=\"$activeClass\">$label</a>";
            }
            ?>
        </div>

        <div class="info-box">
            <strong>💡 Auto-Detection:</strong> SQL queries are automatically detected and beautified without any special configuration!
        </div>

        <div class="demo-section">
            <h2>Formatted SQL Query</h2>
            <p>Original query:</p>
            <div class="code-example"><?php echo htmlspecialchars($query); ?></div>
            <p style="margin-top: 20px;">Formatted output:</p>
            <?php
            $request = new DumpRenderRequest($query, 'web', ['theme' => $themeParam]);
            echo $renderer->render($request);
            ?>
        </div>

        <div class="feature-list">
            <div class="feature-card">
                <h4>Auto-Detection</h4>
                <p>Automatically identifies SELECT, INSERT, UPDATE, DELETE queries</p>
            </div>
            <div class="feature-card">
                <h4>Syntax Highlighting</h4>
                <p>Color-coded keywords, strings, and numbers for readability</p>
            </div>
            <div class="feature-card">
                <h4>Proper Formatting</h4>
                <p>Indentation and line breaks following SQL best practices</p>
            </div>
            <div class="feature-card">
                <h4>Theme Support</h4>
                <p>Works with light/dark themes and auto-switching</p>
            </div>
            <div class="feature-card">
                <h4>Collapsible Output</h4>
                <p>Long queries can be collapsed/expanded for better UX</p>
            </div>
            <div class="feature-card">
                <h4>CLI Compatible</h4>
                <p>Same auto-detection works in CLI environment too</p>
            </div>
        </div>

        <div class="demo-section">
            <h3>Usage in Code</h3>
            <div class="code-example">
// Auto-detection - just dump SQL strings
$sql = "SELECT u.id, u.name FROM users u WHERE u.active = 1";
pd($sql);  // Automatically detected and formatted!

// Or use the dedicated SQL function with bindings
$sql = "SELECT * FROM users WHERE id = ? AND status = ?";
$bindings = [123, 'active'];
pd_sql($sql, $bindings);
            </div>
        </div>
    </div>

    <script>
        (function initialiseTheme() {
            const paramTheme = '<?php echo $themeParam; ?>';
            if (paramTheme === 'auto') {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                return;
            }
            document.documentElement.setAttribute('data-theme', paramTheme);
        })();
    </script>
</body>
</html>

<?php
/**
 * Startup instructions:
 *
 * 1. Run in terminal:
 *    php -S localhost:8082 -t examples/web/sql-highlight.php
 *
 * 2. Visit in browser:
 *    http://localhost:8082
 *    http://localhost:8082?demo=complex
 *    http://localhost:8082?demo=insert
 *    http://localhost:8082?theme=dark
 *
 * 3. Features:
 *    - Automatic SQL detection
 *    - Syntax highlighting (keywords, strings, numbers)
 *    - Proper indentation and formatting
 *    - Theme support (light/dark/auto)
 *    - Collapsible output for long queries
 *    - Interactive navigation
 */
