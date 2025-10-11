<?php

declare(strict_types=1);

/**
 * PrettyDumper 异常处理示例
 *
 * 运行: php examples/cli/exception-handling.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Renderer\CliRenderer;

echo "=== PrettyDumper 异常处理示例 ===\n\n";

// 配置显示异常信息
$configuration = new FormatterConfiguration([
    'expandExceptions' => true,
    'showContext' => true,
    'maxDepth' => 5,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter);

// 示例1: 简单异常
echo "1. 简单异常:\n";
try {
    throw new RuntimeException('数据库连接失败');
} catch (Exception $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// 示例2: 嵌套异常
echo "2. 嵌套异常链:\n";
try {
    try {
        try {
            throw new InvalidArgumentException('无效的参数值');
        } catch (InvalidArgumentException $e) {
            throw new LogicException('业务逻辑错误', 0, $e);
        }
    } catch (LogicException $e) {
        throw new RuntimeException('系统运行错误', 500, $e);
    }
} catch (Exception $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// 示例3: 自定义异常类
echo "3. 自定义异常类:\n";

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

try {
    $errors = [
        'username' => '用户名已存在',
        'email' => '邮箱格式不正确',
        'password' => '密码长度至少8位',
    ];
    throw new ValidationException('表单验证失败', $errors);
} catch (ValidationException $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// 示例4: 包含上下文的异常
echo "4. 包含请求上下文的异常:\n";

function processRequest(array $data): void
{
    if (empty($data['user_id'])) {
        throw new InvalidArgumentException('用户ID不能为空');
    }

    if (!is_numeric($data['amount'])) {
        throw new InvalidArgumentException('金额必须是数字');
    }

    if ($data['amount'] < 0) {
        throw new InvalidArgumentException('金额不能为负数');
    }

    // 模拟处理逻辑
    throw new RuntimeException('处理请求时发生未知错误');
}

try {
    $requestData = [
        'user_id' => '',
        'amount' => '-100',
        'description' => '测试支付',
        'timestamp' => time(),
    ];

    processRequest($requestData);
} catch (Exception $e) {
    // 创建包含请求数据的复合信息
    $context = [
        'exception' => $e,
        'request_data' => $requestData,
        'server_info' => [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ],
    ];

    $request = new DumpRenderRequest($context, 'cli');
    echo $renderer->render($request);
}
echo "\n";

echo "=== 异常处理示例完成 ===\n";