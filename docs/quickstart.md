# Quickstart — Pretty Dumper

Pretty Dumper 提供 CLI 与 Web 渲染两种渠道，用于在调试时输出高可读性的变量、调用栈与异常链信息。以下示例帮助你快速接入项目。

## 安装

```bash
composer require anhoder/pretty-dumper --dev
```

- 安装完成后可直接使用 `vendor/bin/pretty-dump` 命令。
- Web 端静态资源位于 `vendor/anhoder/pretty-dumper/public/assets/`，包含 CSS 与最小化的主题切换脚本。

## CLI 使用示例

```bash
# 默认彩色输出，带类型标注与缩进
pretty-dump --depth=4 "config('app')"

# JSON 输出模式会自动折叠树状结构
pretty-dump --format=json 'json_encode(["id" => 42, "role" => "admin"])'

# 禁用颜色（非 TTY 环境或日志场景）
pretty-dump --no-color --theme=dark "App\\Services\\Report::class"

# 从 STDIN 读取 JSON 并自动解析
echo '{"ok":true}' | pretty-dump --stdin --from=json --format=json

# 让 CLI 直接执行返回值文件
pretty-dump --file=bootstrap/cache/inspect.php --depth=6
```

- `--depth` 控制对象展开深度，默认为 5。
- `--format=json` 会启用 JSON 自动折叠，并在 CLI 中提供截断提示。
- `--stdin` / `--file=PATH` 允许从标准输入或指定 PHP 文件加载数据，默认使用 `--from=php` 执行表达式或返回值。
- `--from=php|json|raw|serialized` 控制输入解析方式，结合 `--stdin` 可快速调试 JSON、序列化或纯文本数据。
- `--context` / `--context-file` 接受 JSON，上下文会注入 `DumpRenderRequest` 以复现请求、环境或变量快照。
- `--max-items`、`--string-limit`、`--indent-style` 等高级参数可覆盖 `FormatterConfiguration`，配合 `--show-meta` / `--no-meta`、`--show-context` / `--no-context` 定制输出细节。
- `--no-color` 或非 TTY 流情况下，渲染器会退化为纯文本输出但保留结构；`--color` 可强制启用 ANSI 颜色。
- CLI 渠道默认隐藏上下文区块，可通过配置中的 `'showContext' => true` 或设置环境变量 `PRETTY_DUMP_SHOW_CONTEXT=1` 重新启用。

## Web 嵌入示例

```php
use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\WebRenderer;
use Anhoder\PrettyDumper\Support\ThemeRegistry;

$formatter = PrettyFormatter::forChannel('web', new FormatterConfiguration());
$renderer = new WebRenderer($formatter, ThemeRegistry::withDefaults());

$request = new DumpRenderRequest($payload, 'web', [
    'theme' => 'auto',
    'showContext' => true,
]);

echo $renderer->render($request);
```

- 请在页面中引入 `public/assets/css/pretty-dump.css` 与 `public/assets/js/pretty-dump.js` 以启用主题切换和无障碍样式。
- 无 JavaScript 环境下会自动回退至 `<details>`/`<summary>` 结构，键盘可访问性已通过 WCAG AA 对比度校验。

## 框架集成

### Laravel

```php
// config/app.php
'providers' => [
    Anhoder\PrettyDumper\Support\Frameworks\LaravelServiceProvider::class,
],
```

- 可在 `config/pretty-dump.php` 中覆盖 `maxDepth`、`theme` 等配置（默认使用包内安全值）。
- 通过 `app('pretty-dump')($value, $options)` 或 facade 调用，即可获得与 CLI 一致的输出。

### Symfony

```php
// config/bundles.php
return [
    Anhoder\PrettyDumper\Support\Frameworks\SymfonyBundle::class => ['all' => true],
];
```

- 在 `services.yaml` 中设置参数：

```yaml
parameters:
  pretty_dumper.config:
    maxDepth: 4
    theme: auto
```

- 服务容器将注入 `pretty_dumper.formatter` 与 `pretty_dumper.cli_renderer`，可在命令或控制器中复用。

## 质量校验

```bash
composer test      # Pest 单元 / 特性 / 性能测试
composer phpstan   # Level max 静态分析
composer mago      # 代码风格与格式化
```

- 性能组测试：`./vendor/bin/pest --group=performance`，确保 10 万元素场景 ≤ 3s。
- 所有渠道输出均默认脱敏敏感字段（password/token/secret 等），请在自定义配置中追加规则以满足业务需求。
