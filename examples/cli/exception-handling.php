<?php

declare(strict_types=1);

/**
 * PrettyDumper Exception Handling Example
 *
 * Run: php examples/cli/exception-handling.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Renderer\CliRenderer;

echo "=== PrettyDumper Exception Handling Example ===\n\n";

// Configure exception display
$configuration = new FormatterConfiguration([
    'expandExceptions' => true,
    'showContext' => true,
    'maxDepth' => 5,
]);

$formatter = PrettyFormatter::forChannel('cli', $configuration);
$renderer = new CliRenderer($formatter);

// Example 1: Simple exception
echo "1. Simple exception:\n";
try {
    throw new RuntimeException('Database connection failed');
} catch (Exception $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// Example 2: Nested exception
echo "2. Nested exception chain:\n";
try {
    try {
        try {
            throw new InvalidArgumentException('Invalid parameter value');
        } catch (InvalidArgumentException $e) {
            throw new LogicException('Business logic error', 0, $e);
        }
    } catch (LogicException $e) {
        throw new RuntimeException('System runtime error', 500, $e);
    }
} catch (Exception $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// Example 3: Custom exception class
echo "3. Custom exception class:\n";

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
        'username' => 'Username already exists',
        'email' => 'Invalid email format',
        'password' => 'Password must be at least 8 characters',
    ];
    throw new ValidationException('Form validation failed', $errors);
} catch (ValidationException $e) {
    $request = new DumpRenderRequest($e, 'cli');
    echo $renderer->render($request);
}
echo "\n";

// Example 4: Exception with context
echo "4. Exception with request context:\n";

function processRequest(array $data): void
{
    if (empty($data['user_id'])) {
        throw new InvalidArgumentException('User ID cannot be empty');
    }

    if (!is_numeric($data['amount'])) {
        throw new InvalidArgumentException('Amount must be a number');
    }

    if ($data['amount'] < 0) {
        throw new InvalidArgumentException('Amount cannot be negative');
    }

    // Simulate processing logic
    throw new RuntimeException('Unknown error occurred while processing request');
}

try {
    $requestData = [
        'user_id' => '',
        'amount' => '-100',
        'description' => 'Test payment',
        'timestamp' => time(),
    ];

    processRequest($requestData);
} catch (Exception $e) {
    // Create compound information with request data
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

echo "=== Exception handling examples completed ===\n";
