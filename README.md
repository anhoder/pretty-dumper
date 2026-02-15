# Pretty Dumper

<div align="center">

**Powerful PHP debugging output tool for CLI and Web environments**

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-pest-ff69b4.svg)](https://pestphp.com/)

</div>

## Introduction

Pretty Dumper is a modern PHP debugging library that provides highly readable output for variables, exceptions, and stack traces. It supports both CLI colored output and Web HTML rendering, making debugging more efficient and intuitive.

### ✨ Core Features

- 🎨 **Dual-Mode Rendering** - CLI colored output & Web HTML interface
- 🌓 **Theme System** - Built-in light/dark themes with auto-switching
- 🔍 **Depth Control** - Smart recursive rendering with circular reference detection
- 🛡️ **Sensitive Data Protection** - Auto-redaction of passwords, tokens, etc.
- 🚀 **High Performance** - Renders 1M elements in ≤ 3 seconds
- ♿ **Accessibility** - WCAG AA compliant
- 🔗 **Framework Integration** - Native Laravel and Symfony support
- 💎 **Advanced Features**:
  - Complete exception chain display
  - SQL auto-detection and beautification
  - JSON auto-parsing and display
  - Diff comparison functionality
  - Context snapshot capture

## Installation

```bash
composer require anhoder/pretty-dumper --dev
```

**Requirements**: PHP ^8.0

## Quick Start

### Global Functions

```php
// Basic usage
pretty_dump($variable);

// Shorthand
pd($variable);

// Dump multiple variables
dump($var1, $var2, $var3);

// Dump and die
dd($variable);

// Dump with options
pretty_dump($variable, [
    'maxDepth' => 5,
    'maxItems' => 100,
    'theme' => 'dark'
]);

// JSON format output (auto-detects and formats JSON)
dumpj($variable);
pdj($variable);     // JSON format with more options
ddj($variable);     // Dump and die with JSON format
```

### CLI Command Line

```bash
# Basic colored output
pretty-dump --depth=4 "config('app')"

# JSON format output
pretty-dump --format=json 'json_encode(["id" => 42])'

# Read from stdin
echo '{"ok":true}' | pretty-dump --stdin --from=json

# Execute PHP file
pretty-dump --file=bootstrap/cache/inspect.php --depth=6

# Custom theme and indentation
pretty-dump --theme=dark --indent-style=tabs --depth=5 "\$data"
```

### API Usage

```php
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;

// Create configuration
$config = new FormatterConfiguration([
    'maxDepth' => 5,
    'maxItems' => 200,
    'stringLengthLimit' => 1000,
    'theme' => 'auto'
]);

// Create formatter and renderer
$formatter = PrettyFormatter::forChannel('cli', $config);
$renderer = new CliRenderer($formatter);

// Render output
echo $renderer->render($value);
```

## Configuration Options

### FormatterConfiguration Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `maxDepth` | int | CLI: 6<br>Web: 10 | Maximum depth for object expansion |
| `maxItems` | int | CLI: 500<br>Web: 5000 | Maximum items to display in arrays/objects |
| `stringLengthLimit` | int | 500000 | String length limit (bytes) |
| `theme` | string | 'auto' | Theme: auto/light/dark |
| `redactionRules` | array | See below | Sensitive data redaction rules |
| `indentStyle` | string | 'spaces' | Indentation style: spaces/tabs |
| `indentSize` | int | CLI: 0<br>Web: 2 | Indentation size |
| `autoDetectJson` | bool | false | Auto-detect and format JSON strings |
| `showPerformanceMetrics` | bool | false | Show rendering time and statistics |

### CLI Command Options

```
--help                     Show help information
--depth=N                  Object expansion depth
--format=json|cli          Output format
--theme=light|dark|auto    Theme selection
--color / --no-color       Enable/disable colors
--stdin                    Read from stdin
--file=PATH                Read from file
--from=php|json|raw        Input format
--max-items=N              Maximum items limit
--string-limit=N           String length limit
--expand-exceptions        Expand exception details
--show-context             Show context information
--indent-style=spaces|tabs Indentation style
--indent-size=N            Indentation size
```

### Sensitive Data Redaction

Default redaction rules (case-insensitive field name matching):

```php
[
    'password',
    'passwd',
    'pwd',
    'secret',
    'token',
    'api_key',
    'apikey',
    'access_token',
    'refresh_token',
    'private_key',
    'auth'
]
```

Custom redaction rules:

```php
$config = new FormatterConfiguration([
    'redactionRules' => [
        'creditCard',
        'ssn',
        'phoneNumber'
    ]
]);
```

### Auto-Detection Features

Pretty Dumper automatically detects and formats special data types:

#### SQL Auto-Detection ✨
SQL queries are automatically detected and beautified:

```php
// Automatic detection - just dump the SQL string
$sql = "SELECT u.id, u.name FROM users u WHERE u.status = 'active' ORDER BY u.created_at DESC";
pd($sql);  // Automatically detected as SQL, formatted and highlighted!

// Works with complex queries too
$complexSql = "SELECT u.id, o.id FROM users u JOIN orders o ON u.id = o.user_id";
pd($complexSql);
```

Supports:
- SELECT, INSERT, UPDATE, DELETE queries
- Complex JOIN statements
- Aggregation with GROUP BY and HAVING
- Common Table Expressions (CTE)
- Syntax highlighting for keywords, strings, and numbers
- Works in both CLI and Web environments

#### JSON Auto-Detection
JSON strings can be automatically detected and formatted:

```php
// Using dumpj() - auto-detects JSON
$jsonString = '{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}],"count":2}';
dumpj($jsonString);

// Or with manual option
pd($jsonString, ['autoDetectJson' => true]);

// With shorthand functions
pdj($data);        // JSON format
ddj($data);        // JSON format and die
```

Features:
- Automatic JSON validation
- Syntax highlighting (keys, strings, numbers, booleans)
- Unicode and emoji support
- Nested structure handling
- Graceful fallback for invalid JSON

### Diff Comparison ✨ NEW

Compare two values and visualize differences:

```php
// Basic diff
use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;

$oldData = ['name' => 'John', 'age' => 30];
$newData = ['name' => 'John', 'age' => 31, 'city' => 'NYC'];

pd_diff($oldData, $newData);

// Diff with auto-detected JSON
$oldJson = '{"name":"Bob","age":25,"skills":["PHP"]}';
$newJson = '{"name":"Bob","age":26,"skills":["PHP","JavaScript"]}';
pd_diff($oldJson, $newJson);

// Auto-diff with last value
$value1 = ['count' => 10];
pd_auto_diff($value1);    // Store first value

$value2 = ['count' => 15];
pd_auto_diff($value2);    // Compare with stored value

// Diff and die
pdd_diff($oldData, $newData);
```

Output marks:
- 🟢 Added keys/values
- 🔴 Removed keys/values
- 🟡 Modified values
- ⚪ Unchanged values

### Conditional Dumping ✨ NEW

Dump only when conditions are met:

```php
// pd_when - dump when condition is true
$data = ['user' => 'Alice', 'status' => 'active'];
pd_when($data, fn($d) => $d['status'] === 'active');

// pd_when with boolean condition
$error = ['code' => 500, 'message' => 'Server error'];
pd_when($error, false);  // Won't dump

// pd_assert - assertion-based dumping
$response = ['status' => 200, 'data' => ['id' => 1]];
pd_assert($response, fn($r) => $r['status'] === 200, 'HTTP status should be 200');

// pd_assert with message
$user = ['name' => 'Bob', 'age' => 25];
pd_assert($user, fn($u) => $u['age'] >= 18, 'User must be an adult');

// Die after assertion
pdd_assert($config, fn($c) => $c['debug'] === true, 'Debug mode must be enabled');

// Die when condition met
pdd_when($criticalError, true);
```

### Dump History ✨ NEW

Track and compare values across code execution:

```php
// Compare with last dumped value at this location
$value = ['count' => 10];
pd_auto_diff($value);  // First run - just dumps

$value = ['count' => 15];
pd_auto_diff($value);  // Shows diff from previous value

// Clear all history
pd_clear_history();

// Clear specific location
pd_clear_history(__DIR__ . '/script.php:42');
```

## Framework Integration

### Laravel

Register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Anhoder\PrettyDumper\Support\Frameworks\LaravelServiceProvider::class,
],
```

Usage:

```php
// Via container
app('pretty-dump')($value, ['maxDepth' => 4]);

// Use global functions directly
pd($user);
dd($request->all());
```

### Symfony

Register the Bundle in `config/bundles.php`:

```php
return [
    // ...
    Anhoder\PrettyDumper\Support\Frameworks\SymfonyBundle::class => ['all' => true],
];
```

### Web Environment

```

Output debug information:

```php
// Auto-detect environment and output
pd($data);

// Or force Web rendering
$formatter = PrettyFormatter::forChannel('web');
$renderer = new WebRenderer($formatter);
echo $renderer->render($data);
```

## Advanced Features

### Exception Handling

```php
try {
    throw new \RuntimeException('Database connection failed', 500);
} catch (\Exception $e) {
    pd($e);  // Complete exception chain and stack trace
}
```

Output includes:
- Exception message and code
- Complete exception chain
- Stack trace (with file and line numbers)
- Variable snapshots

### SQL Detection ✨ Automatic

SQL queries are automatically detected and beautified:

```php
// Just dump SQL - no special function needed!
$sql = "SELECT u.id, u.name FROM users u WHERE u.status = 'active' ORDER BY u.created_at DESC";
pd($sql);  // Auto-detected as SQL and formatted!

// Complex queries work too
$complexSql = "SELECT 
    u.id,
    u.name,
    o.id as order_id,
    o.total
FROM users u
INNER JOIN orders o ON u.id = o.user_id
WHERE u.status = 'active'
ORDER BY o.created_at DESC";
pd($complexSql);

// Inside arrays
$data = [
    'user_query' => "SELECT * FROM users WHERE id = ?",
    'order_query' => "SELECT * FROM orders WHERE user_id = ?",
];
pd($data);  // All SQL strings auto-detected
```

Features:
- Automatic detection of SELECT, INSERT, UPDATE, DELETE queries
- Syntax highlighting (keywords, strings, numbers)
- Proper indentation and formatting
- Works seamlessly in both CLI and Web environments
- No configuration needed - works out of the box!

For specialized SQL operations (with bindings, EXPLAIN, etc.), use `pd_sql()`:

```php
$sql = "SELECT * FROM users WHERE id = ? AND status = ?";
$bindings = [123, 'active'];
pd_sql($sql, $bindings, $pdo);  // With PDO connection for EXPLAIN
```

### JSON Auto-Detection ✨

JSON strings can be automatically detected and formatted using `dumpj()` or the `autoDetectJson` option:

```php
$jsonString = '{"users":[{"id":1,"name":"Alice"}]}';
dumpj($jsonString);  // Auto-detected as JSON

// Or with manual option
pd($jsonString, ['autoDetectJson' => true]);

// Works in arrays too
$apiResponse = [
    'user_data' => '{"id":1,"name":"Bob"}',
    'config' => '{"theme":"dark","language":"zh"}',
];
pd($apiResponse, ['autoDetectJson' => true]);
```

### Diff Comparison

```php
use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;

$oldData = ['name' => 'John', 'age' => 30];
$newData = ['name' => 'John', 'age' => 31, 'city' => 'NYC'];

pd(DiffTransformer::diff($oldData, $newData));
```

Output marks:
- 🟢 Added keys/values
- 🔴 Removed keys/values
- 🟡 Modified values
- ⚪ Unchanged values

### Context Snapshots

```php
use Anhoder\PrettyDumper\Context\ContextSnapshot;
use Anhoder\PrettyDumper\Context\DefaultContextCollector;

$collector = new DefaultContextCollector();
$snapshot = $collector->collect();

pd($snapshot);  // Includes request info, environment variables, stack, etc.
```

## Testing

```bash
# Run all tests
composer test

# Run specific test group
./vendor/bin/pest --group=performance

# Static code analysis
composer phpstan

# Code style check
composer mago
```

## Project Structure

```
src/
├── helpers.php              # Global helper functions
└── PrettyDumper/
    ├── Context/             # Context management
    │   ├── ContextSnapshot.php
    │   └── DefaultContextCollector.php
    ├── Formatter/           # Formatting engine
    │   ├── PrettyFormatter.php
    │   ├── FormatterConfiguration.php
    │   └── Transformers/    # Data transformers
    │       ├── ExceptionTransformer.php
    │       ├── JsonTransformer.php
    │       ├── SqlTransformer.php
    │       └── DiffTransformer.php
    ├── Renderer/            # Rendering layer
    │   ├── CliRenderer.php
    │   ├── WebRenderer.php
    │   └── DiffRenderer.php
    ├── Storage/             # Storage engine
    │   ├── MemoryStorage.php
    │   ├── FileStorage.php
    │   └── DumpHistoryStorage.php
    └── Support/             # Support modules
        ├── Frameworks/      # Framework integration
        │   ├── LaravelServiceProvider.php
        │   └── SymfonyBundle.php
        └── Themes/          # Theme system
            ├── ThemeRegistry.php
            └── ThemeProfile.php

public/assets/
├── css/pretty-dump.css      # Web styles
└── js/pretty-dump.js        # Interactive scripts

examples/
└── run-examples.php         # Interactive examples

tests/
└── FeatureTest.php          # Feature tests
```

## Performance

- ✅ Renders 1M elements in ≤ 3 seconds
- ✅ Automatic circular reference detection
- ✅ Depth and item count protection
- ✅ Large string truncation mechanism

## Browser Compatibility

Web rendering supports all modern browsers:

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Opera 76+

No-JavaScript environments can use native `<details>`/`<summary>` expansion.

## Contributing

Issues and Pull Requests are welcome!

## License

MIT License

---

<div align="center">
Made with ❤️ by <a href="https://github.com/anhoder">anhoder</a>
</div>
