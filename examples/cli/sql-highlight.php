#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PrettyDumper SQL Auto-Detection Example
 *
 * Demonstrates automatic SQL detection and beautification in CLI
 *
 * Run: php examples/cli/sql-highlight.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper SQL Auto-Detection Demo\n";
echo str_repeat("=", 60) . "\n\n";

$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'showContext' => false,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter, stream_isatty(STDOUT));

// Example 1: Simple SELECT query
echo "📋 Example 1: Simple SELECT query\n";
echo str_repeat("-", 40) . "\n";
$simpleSelect = "SELECT u.id, u.name, u.email FROM users u WHERE u.active = 1 LIMIT 10";
$request = new DumpRenderRequest($simpleSelect, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 2: Complex JOIN query
echo "📋 Example 2: Complex JOIN query with multiple tables\n";
echo str_repeat("-", 40) . "\n";
$complexJoin = "SELECT 
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
LIMIT 50";
$request = new DumpRenderRequest($complexJoin, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 3: INSERT statement
echo "📋 Example 3: INSERT statement\n";
echo str_repeat("-", 40) . "\n";
$insertStatement = "INSERT INTO products (name, description, price, category, in_stock, created_at) VALUES ('New Product', 'Product description', 99.99, 'Electronics', 1, NOW())";
$request = new DumpRenderRequest($insertStatement, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 4: UPDATE with WHERE clause
echo "📋 Example 4: UPDATE with WHERE clause\n";
echo str_repeat("-", 40) . "\n";
$updateStatement = "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = 123 AND active = 1";
$request = new DumpRenderRequest($updateStatement, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 5: DELETE statement
echo "📋 Example 5: DELETE statement\n";
echo str_repeat("-", 40) . "\n";
$deleteStatement = "DELETE FROM sessions WHERE expired_at < NOW() OR user_id IS NULL";
$request = new DumpRenderRequest($deleteStatement, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 6: Aggregation query
echo "📋 Example 6: Aggregation with GROUP BY and HAVING\n";
echo str_repeat("-", 40) . "\n";
$aggregation = "SELECT 
    u.department,
    COUNT(*) as total_users,
    AVG(u.salary) as avg_salary,
    MAX(u.salary) as max_salary,
    MIN(u.salary) as min_salary
FROM users u
WHERE u.hired_date >= '2020-01-01'
GROUP BY u.department
HAVING COUNT(*) > 5
ORDER BY avg_salary DESC";
$request = new DumpRenderRequest($aggregation, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 7: SQL with CTE (Common Table Expression)
echo "📋 Example 7: SQL with CTE (Common Table Expression)\n";
echo str_repeat("-", 40) . "\n";
$cteQuery = "WITH 
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
ORDER BY ms.month, tp.total_sales DESC";
$request = new DumpRenderRequest($cteQuery, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 8: SQL inside array
echo "📋 Example 8: SQL queries inside array\n";
echo str_repeat("-", 40) . "\n";
$queriesArray = [
    'get_user' => "SELECT * FROM users WHERE id = ? LIMIT 1",
    'get_orders' => "SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.user_id = ? ORDER BY o.created_at DESC",
    'get_stats' => "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM transactions WHERE user_id = ? AND status = 'completed'",
];
$request = new DumpRenderRequest($queriesArray, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 9: Non-SQL string (to show it's not detected)
echo "📋 Example 9: Regular string (non-SQL, not auto-detected)\n";
echo str_repeat("-", 40) . "\n";
$nonSql = "This is just a regular string that happens to contain words like SELECT and FROM, but it's not a SQL query.";
$request = new DumpRenderRequest($nonSql, 'cli');
echo $renderer->render($request) . "\n\n";

// Example 10: Edge case - SQL-like but not starting with keyword
echo "📋 Example 10: SQL-like string (not detected)\n";
echo str_repeat("-", 40) . "\n";
$sqlLikeButNot = "The query to SELECT users FROM database needs to be tested";
$request = new DumpRenderRequest($sqlLikeButNot, 'cli');
echo $renderer->render($request) . "\n\n";

echo str_repeat("=", 60) . "\n";
echo "✅ SQL auto-detection demo completed!\n\n";

echo "🎯 Key features demonstrated:\n";
echo "1. ✅ Automatic detection of SELECT, INSERT, UPDATE, DELETE queries\n";
echo "2. ✅ Query formatting with proper indentation\n";
echo "3. ✅ SQL keyword highlighting (SELECT, FROM, WHERE, etc.)\n";
echo "4. ✅ String literal highlighting\n";
echo "5. ✅ Number highlighting\n";
echo "6. ✅ Works seamlessly with array/object structures\n";
echo "7. ✅ Non-SQL strings remain unchanged\n";
echo "8. ✅ No configuration needed - works automatically!\n\n";

echo "💡 Usage tips:\n";
echo "- Just use pd() or pretty_dump() with any SQL string\n";
echo "- SQL is automatically detected by keyword patterns\n";
echo "- Complex queries (JOINs, subqueries, CTEs) are fully supported\n";
echo "- Works in both CLI and Web environments\n";
echo "- No need to use pd_sql() for simple queries anymore!\n\n";
