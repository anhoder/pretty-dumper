# Pretty Dumper

<div align="center">

**PHP 强大的调试输出工具 - CLI 和 Web 环境通用**

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-pest-ff69b4.svg)](https://pestphp.com/)

</div>

## 简介

Pretty Dumper 是一个现代化的 PHP 调试工具库，提供高可读性的变量、异常和调用栈信息输出。支持 CLI 彩色输出和 Web HTML 渲染，让调试过程更加高效和直观。

### ✨ 核心特性

- 🎨 **双模式渲染** - CLI 彩色输出 & Web HTML 界面
- 🌓 **主题系统** - 内置浅色/深色主题，支持自动切换
- 🔍 **深度控制** - 智能递归渲染，支持循环引用检测
- 🛡️ **敏感信息保护** - 自动脱敏密码、令牌等敏感字段
- 🚀 **高性能** - 100万元素渲染 ≤ 3秒
- ♿ **无障碍友好** - 符合 WCAG AA 标准
- 🔗 **框架集成** - Laravel 和 Symfony 原生支持
- 💎 **高级特性**:
  - 异常链完整展示
  - SQL 自动识别和美化
  - JSON 自动解析和展示
  - Diff 对比功能
  - 上下文快照捕获

## 安装

```bash
composer require anhoder/pretty-dumper --dev
```

**系统要求**: PHP ^8.0

## 快速开始

### 全局函数

```php
// 基础用法
pretty_dump($variable);

// 简写方式
pd($variable);

// 输出多个变量
dump($var1, $var2, $var3);

// 输出并终止脚本
dd($variable);

// 带选项的输出
pretty_dump($variable, [
    'maxDepth' => 5,
    'maxItems' => 100,
    'theme' => 'dark'
]);

// JSON 格式化输出（自动识别和格式化 JSON）
dumpj($variable);
pdj($variable);     // JSON 格式，带更多选项
ddj($variable);     // JSON 格式并退出
```

### CLI 命令行

```bash
# 基本彩色输出
pretty-dump --depth=4 "config('app')"

# JSON 格式输出
pretty-dump --format=json 'json_encode(["id" => 42])'

# 从标准输入读取
echo '{"ok":true}' | pretty-dump --stdin --from=json

# 执行 PHP 文件
pretty-dump --file=bootstrap/cache/inspect.php --depth=6

# 自定义主题和缩进
pretty-dump --theme=dark --indent-style=tabs --depth=5 "\$data"
```

### API 调用

```php
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;

// 创建配置
$config = new FormatterConfiguration([
    'maxDepth' => 5,
    'maxItems' => 200,
    'stringLengthLimit' => 1000,
    'theme' => 'auto'
]);

// 创建格式化器和渲染器
$formatter = PrettyFormatter::forChannel('cli', $config);
$renderer = new CliRenderer($formatter);

// 渲染输出
echo $renderer->render($value);
```

## 配置选项

### FormatterConfiguration 参数

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `maxDepth` | int | CLI: 6<br>Web: 10 | 对象展开的最大深度 |
| `maxItems` | int | CLI: 500<br>Web: 5000 | 数组/对象中显示的最大项目数 |
| `stringLengthLimit` | int | 500000 | 字符串长度限制（字节） |
| `theme` | string | 'auto' | 主题: auto/light/dark |
| `redactionRules` | array | 见下文 | 敏感信息脱敏规则 |
| `indentStyle` | string | 'spaces' | 缩进风格: spaces/tabs |
| `indentSize` | int | CLI: 0<br>Web: 2 | 缩进大小 |
| `autoDetectJson` | bool | false | 自动识别并格式化 JSON 字符串 |
| `showPerformanceMetrics` | bool | false | 显示渲染时间和统计信息 |

### CLI 命令选项

```
--help                     显示帮助信息
--depth=N                  对象展开深度
--format=json|cli          输出格式
--theme=light|dark|auto    主题选择
--color / --no-color       启用/禁用颜色
--stdin                    从标准输入读取
--file=PATH                从文件读取
--from=php|json|raw        输入格式
--max-items=N              最大项目数限制
--string-limit=N           字符串长度限制
--expand-exceptions        展开异常详情
--show-context             显示上下文信息
--indent-style=spaces|tabs 缩进风格
--indent-size=N            缩进大小
```

### 敏感信息脱敏

默认脱敏规则（匹配字段名，不区分大小写）:

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

自定义脱敏规则:

```php
$config = new FormatterConfiguration([
    'redactionRules' => [
        'creditCard',
        'ssn',
        'phoneNumber'
    ]
]);
```

### 自动识别功能 ✨

Pretty Dumper 自动识别和格式化特殊数据类型：

#### SQL 自动识别 ✨
SQL 查询会自动被识别并美化：

```php
// 直接输出 SQL 即可 - 无需特殊函数！
$sql = "SELECT u.id, u.name FROM users u WHERE u.status = 'active' ORDER BY u.created_at DESC";
pd($sql);  // 自动识别为 SQL，格式化并高亮显示！

// 复杂查询也支持
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

// 在数组中也支持
$data = [
    'user_query' => "SELECT * FROM users WHERE id = ?",
    'order_query' => "SELECT * FROM orders WHERE user_id = ?",
];
pd($data);  // 所有 SQL 字符串自动识别
```

支持:
- 自动识别 SELECT、INSERT、UPDATE、DELETE 查询
- 语法高亮（关键字、字符串、数字）
- 合适的缩进和格式化
- 在 CLI 和 Web 环境中无缝工作
- 无需配置 - 开箱即用！

如需专门的 SQL 操作（带绑定、EXPLAIN 等），使用 `pd_sql()`:

```php
$sql = "SELECT * FROM users WHERE id = ? AND status = ?";
$bindings = [123, 'active'];
pd_sql($sql, $bindings, $pdo);  // 带 PDO 连接执行 EXPLAIN
```

#### JSON 自动识别 ✨
JSON 字符串可以使用 `dumpj()` 或 `autoDetectJson` 选项自动识别和格式化：

```php
$jsonString = '{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}],"count":2}';
dumpj($jsonString);  // 自动识别为 JSON

// 或使用手动选项
pd($jsonString, ['autoDetectJson' => true]);

// 支持在数组中使用
$apiResponse = [
    'user_data' => '{"id":1,"name":"Bob"}',
    'config' => '{"theme":"dark","language":"zh"}',
];
pd($apiResponse, ['autoDetectJson' => true]);
```

特性:
- 自动 JSON 验证
- 语法高亮（键、字符串、数字、布尔值）
- Unicode 和 emoji 支持
- 嵌套结构处理
- 对无效 JSON 的优雅降级

### Diff 对比功能 ✨ NEW

对比两个值并可视化差异：

```php
// 基础 diff
use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;

$oldData = ['name' => 'John', 'age' => 30];
$newData = ['name' => 'John', 'age' => 31, 'city' => '纽约'];

pd_diff($oldData, $newData);

// 带自动识别 JSON 的 diff
$oldJson = '{"name":"Bob","age":25,"skills":["PHP"]}';
$newJson = '{"name":"Bob","age":26,"skills":["PHP","JavaScript"]}';
pd_diff($oldJson, $newJson);

// 自动对比上次的值
$value1 = ['count' => 10];
pd_auto_diff($value1);    // 存储第一个值

$value2 = ['count' => 15];
pd_auto_diff($value2);    // 与存储的值对比

// diff 并退出
pdd_diff($oldData, $newData);
```

输出标记:
- 🟢 添加的键/值
- 🔴 删除的键/值
- 🟡 修改的值
- ⚪ 未变化的值

### 条件输出功能 ✨ NEW

仅在条件满足时输出调试信息：

```php
// pd_when - 条件为 true 时输出
$data = ['user' => 'Alice', 'status' => 'active'];
pd_when($data, fn($d) => $d['status'] === 'active');

// pd_when 带布尔条件
$error = ['code' => 500, 'message' => '服务器错误'];
pd_when($error, false);  // 不会输出

// pd_assert - 基于断言的输出
$response = ['status' => 200, 'data' => ['id' => 1]];
pd_assert($response, fn($r) => $r['status'] === 200, 'HTTP 状态码应为 200');

// pd_assert 带消息
$user = ['name' => 'Bob', 'age' => 25];
pd_assert($user, fn($u) => $u['age'] >= 18, '用户必须是成年人');

// 断言后退出
pdd_assert($config, fn($c) => $c['debug'] === true, '调试模式必须启用');

// 条件满足时退出
pdd_when($criticalError, true);
```

### 输出历史功能 ✨ NEW

在代码执行中跟踪和对比值：

```php
// 与同一位置上次输出的值对比
$value = ['count' => 10];
pd_auto_diff($value);  // 第一次运行 - 仅输出

$value = ['count' => 15];
pd_auto_diff($value);  // 显示与上一次值的差异

// 清除所有历史
pd_clear_history();

// 清除特定位置
pd_clear_history(__DIR__ . '/script.php:42');
```

## 框架集成

### Laravel

在 `config/app.php` 中注册服务提供者：

```php
'providers' => [
    // ...
    Anhoder\PrettyDumper\Support\Frameworks\LaravelServiceProvider::class,
],
```

使用：

```php
// 通过容器
app('pretty-dump')($value, ['maxDepth' => 4]);

// 直接使用全局函数
pd($user);
dd($request->all());
```

### Symfony

在 `config/bundles.php` 中注册 Bundle：

```php
return [
    // ...
    Anhoder\PrettyDumper\Support\Frameworks\SymfonyBundle::class => ['all' => true],
];
```

### Web 环境

输出调试信息：

```php
// 自动检测环境并输出
pd($data);

// 或强制使用 Web 渲染
$formatter = PrettyFormatter::forChannel('web');
$renderer = new WebRenderer($formatter);
echo $renderer->render($data);
```

## 高级特性

### 异常处理

```php
try {
    throw new \RuntimeException('Database connection failed', 500);
} catch (\Exception $e) {
    pd($e);  // 完整展示异常链和堆栈跟踪
}
```

输出包含：
- 异常消息和代码
- 完整的异常链
- 堆栈跟踪（带文件和行号）
- 变量快照

### SQL 识别 ✨ 自动

SQL 查询会自动被识别并美化：

```php
// 直接输出 SQL - 无需特殊函数！
$sql = "SELECT u.id, u.name FROM users u WHERE u.status = 'active' ORDER BY u.created_at DESC";
pd($sql);  // 自动识别为 SQL，格式化并高亮显示！

// 复杂查询也支持
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

// 在数组中也支持
$data = [
    'user_query' => "SELECT * FROM users WHERE id = ?",
    'order_query' => "SELECT * FROM orders WHERE user_id = ?",
];
pd($data);  // 所有 SQL 字符串自动识别
```

功能:
- 自动识别 SELECT、INSERT、UPDATE、DELETE 查询
- 语法高亮（关键字、字符串、数字）
- 合适的缩进和格式化
- 在 CLI 和 Web 环境中无缝工作
- 无需配置 - 开箱即用！

如需专门的 SQL 操作（带绑定、EXPLAIN 等），使用 `pd_sql()`:

```php
$sql = "SELECT * FROM users WHERE id = ? AND status = ?";
$bindings = [123, 'active'];
pd_sql($sql, $bindings, $pdo);  // 带 PDO 连接执行 EXPLAIN
```

### Diff 对比

```php
use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;

$oldData = ['name' => 'John', 'age' => 30];
$newData = ['name' => 'John', 'age' => 31, 'city' => 'NYC'];

pd(DiffTransformer::diff($oldData, $newData));
```

输出会标记：
- 🟢 添加的键值
- 🔴 删除的键值
- 🟡 修改的值
- ⚪ 未变化的值

### 上下文快照

```php
use Anhoder\PrettyDumper\Context\ContextSnapshot;
use Anhoder\PrettyDumper\Context\DefaultContextCollector;

$collector = new DefaultContextCollector();
$snapshot = $collector->collect();

pd($snapshot);  // 包含请求信息、环境变量、堆栈等
```

## 测试

```bash
# 运行所有测试
composer test

# 运行特定测试组
./vendor/bin/pest --group=performance

# 静态代码分析
composer phpstan

# 代码风格检查
composer mago
```

## 项目结构

```
src/
├── helpers.php              # 全局助手函数
└── PrettyDumper/
    ├── Context/             # 上下文管理
    │   ├── ContextSnapshot.php
    │   └── DefaultContextCollector.php
    ├── Formatter/           # 格式化引擎
    │   ├── PrettyFormatter.php
    │   ├── FormatterConfiguration.php
    │   └── Transformers/    # 数据转换器
    │       ├── ExceptionTransformer.php
    │       ├── JsonTransformer.php
    │       ├── SqlTransformer.php
    │       └── DiffTransformer.php
    ├── Renderer/            # 渲染层
    │   ├── CliRenderer.php
    │   ├── WebRenderer.php
    │   └── DiffRenderer.php
    ├── Storage/             # 存储引擎
    │   ├── MemoryStorage.php
    │   ├── FileStorage.php
    │   └── DumpHistoryStorage.php
    └── Support/             # 支持模块
        ├── Frameworks/      # 框架集成
        │   ├── LaravelServiceProvider.php
        │   └── SymfonyBundle.php
        └── Themes/          # 主题系统
            ├── ThemeRegistry.php
            └── ThemeProfile.php

public/assets/
├── css/pretty-dump.css      # Web 样式
└── js/pretty-dump.js        # 交互脚本

examples/
└── run-examples.php         # 交互式示例

tests/
└── FeatureTest.php          # 功能测试
```

## 性能

- ✅ 100万元素渲染 ≤ 3秒
- ✅ 自动循环引用检测
- ✅ 深度和项目数限制保护
- ✅ 大字符串截断机制

## 浏览器兼容性

Web 渲染支持所有现代浏览器：

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Opera 76+

无 JavaScript 环境可使用 `<details>`/`<summary>` 原生展开功能。

## 贡献

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License

---

<div align="center">
Made with ❤️ by <a href="https://github.com/anhoder">anhoder</a>
</div>
