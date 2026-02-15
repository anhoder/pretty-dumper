#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Context\DefaultContextCollector;
use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "\n🎨 PrettyDumper Context Snapshot Demo\n";
echo str_repeat("=", 60) . "\n\n";

$configuration = new FormatterConfiguration([
    'maxDepth' => 3,
    'showContext' => true,
    'stackLimit' => 10,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter, stream_isatty(STDOUT));

echo "📋 Example 1: Basic context collection\n";
echo str_repeat("-", 40) . "\n";
$data1 = ['user_id' => 123, 'action' => 'login'];
$request1 = new DumpRenderRequest($data1, 'cli');
echo $renderer->render($request1);

echo "\n📋 Example 2: Using ContextSnapshot directly\n";
echo str_repeat("-", 40) . "\n";
$collector = new DefaultContextCollector();
$snapshot = $collector->collect();
pd($snapshot);

echo "\n📋 Example 3: Context with nested data\n";
echo str_repeat("-", 40) . "\n";
$data3 = [
    'user' => ['id' => 1, 'name' => 'Alice'],
    'action' => 'update_profile',
    'changes' => ['email' => 'new@email.com'],
];
$request3 = new DumpRenderRequest($data3, 'cli');
echo $renderer->render($request3);

echo "\n📋 Example 4: Context with exception\n";
echo str_repeat("-", 40) . "\n";
try {
    throw new RuntimeException('Database connection failed', 500);
} catch (Exception $e) {
    $errorContext = [
        'exception' => $e,
        'user_id' => 42,
        'request_time' => date('Y-m-d H:i:s'),
    ];
    $request4 = new DumpRenderRequest($errorContext, 'cli');
    echo $renderer->render($request4);
}

echo "\n📋 Example 5: Context with custom stack limit\n";
echo str_repeat("-", 40) . "\n";
$config5 = new FormatterConfiguration([
    'maxDepth' => 2,
    'showContext' => true,
    'stackLimit' => 5,
]);
$formatter5 = PrettyFormatter::forChannel('cli', $config5);
$renderer5 = new CliRenderer($formatter5, stream_isatty(STDOUT));
$data5 = ['config' => ['key1' => 'value1', 'key2' => 'value2']];
$request5 = new DumpRenderRequest($data5, 'cli');
echo $renderer5->render($request5);

echo "\n📋 Example 6: Context with sensitive data redaction\n";
echo str_repeat("-", 40) . "\n";
use Anhoder\PrettyDumper\Context\RedactionRule;

$config6 = new FormatterConfiguration([
    'maxDepth' => 2,
    'showContext' => true,
    'redactionRules' => [
        RedactionRule::forPattern('/password/i', '***'),
        RedactionRule::forPattern('/token/i', '***'),
    ],
]);
$formatter6 = PrettyFormatter::forChannel('cli', $config6);
$renderer6 = new CliRenderer($formatter6, stream_isatty(STDOUT));
$data6 = [
    'user' => 'alice',
    'password' => 'secret123',
    'api_token' => 'abc123xyz',
    'email' => 'alice@example.com',
];
$request6 = new DumpRenderRequest($data6, 'cli');
echo $renderer6->render($request6);

echo "\n📋 Example 7: Comparing snapshots\n";
echo str_repeat("-", 40) . "\n";
$collector7 = new DefaultContextCollector();
$snapshot7a = $collector7->collect();
usleep(10000);
$snapshot7b = $collector7->collect();

$comparison = [
    'snapshot_before' => $snapshot7a,
    'snapshot_after' => $snapshot7b,
    'time_delta_ms' => 10,
];
$request7 = new DumpRenderRequest($comparison, 'cli');
echo $renderer->render($request7);

echo "\n📋 Example 8: Environment variables in context\n";
echo str_repeat("-", 40) . "\n";
$envData = [
    'php_version' => PHP_VERSION,
    'os' => PHP_OS_FAMILY,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
    'path_info' => $_SERVER['PATH'] ?? '',
];
$request8 = new DumpRenderRequest($envData, 'cli');
echo $renderer->render($request8);

echo "\n📋 Example 9: Request context simulation\n";
echo str_repeat("-", 40) . "\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['QUERY_STRING'] = 'page=2';

$request9 = new DumpRenderRequest(['test' => 'data'], 'cli');
echo $renderer->render($request9);

echo "\n📋 Example 10: Context with performance metrics\n";
echo str_repeat("-", 40) . "\n";
$start = microtime(true);
$processed = [];
for ($i = 0; $i < 1000; $i++) {
    $processed[] = $i * 2;
}
$end = microtime(true);

$perfData = [
    'processed_count' => count($processed),
    'execution_time_ms' => round(($end - $start) * 1000, 2),
    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
];
$config10 = new FormatterConfiguration([
    'maxDepth' => 2,
    'showContext' => true,
    'showPerformanceMetrics' => true,
]);
$formatter10 = PrettyFormatter::forChannel('cli', $config10);
$renderer10 = new CliRenderer($formatter10, stream_isatty(STDOUT));
$request10 = new DumpRenderRequest($perfData, 'cli');
echo $renderer10->render($request10);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Context snapshot demo completed!\n\n";

echo "🎯 Key features demonstrated:\n";
echo "1. ✅ Automatic context collection (request, env, variables, stack)\n";
echo "2. ✅ Stack trace with file and line numbers\n";
echo "3. ✅ Integration with exception handling\n";
echo "4. ✅ Configurable stack depth limit\n";
echo "5. ✅ Sensitive data redaction (passwords, tokens)\n";
echo "6. ✅ Performance metrics display\n";
echo "7. ✅ Environment information capture\n";
echo "8. ✅ Works seamlessly in both CLI and Web\n\n";

echo "💡 Configuration options:\n";
echo "- showContext: Enable/disable context collection\n";
echo "- stackLimit: Maximum stack frames to display\n";
echo "- redactionRules: Patterns for sensitive data masking\n";
echo "- showPerformanceMetrics: Show rendering time\n\n";
