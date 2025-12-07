<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter;

use Anhoder\PrettyDumper\Context\RedactionRule;

final class FormatterConfiguration
{
    private int $maxDepth;

    private int $maxItems;

    private int $maxItemsHardLimit;

    private int $stringLengthLimit;

    private bool $expandExceptions;

    private bool $showContext;

    private bool $showPerformanceMetrics;

    private string $theme;

    /**
     * @var list<RedactionRule>
     */
    private array $redactionRules;

    private int $stackLimit;

    private int $messageLimit;

    private string $indentStyle;

    private int $indentSize;

    /**
     * @var array<string, mixed>
     */
    private array $extras;

    /**
     * @param array<string, mixed> $overrides
     * @param string|null $channel The channel (cli or web) for channel-specific defaults
     */
    public function __construct(array $overrides = [], ?string $channel = null)
    {
        $overrides = self::sanitizeOverrides($overrides);

        // Set maxDepth based on channel: CLI defaults to 3, Web defaults to 5
        $defaultMaxDepth = self::defaultMaxDepth($channel);
        $this->maxDepth = self::readPositiveInt($overrides, 'maxDepth', $defaultMaxDepth);

        // Set maxItems based on channel: CLI defaults to 100, Web defaults to 2000
        $defaultMaxItems = self::defaultMaxItems($channel);
        $this->maxItems = self::readPositiveInt($overrides, 'maxItems', $defaultMaxItems);
        $this->maxItemsHardLimit = self::readPositiveInt($overrides, 'maxItemsHardLimit', 10000);
        $this->stringLengthLimit = self::readPositiveInt($overrides, 'stringLengthLimit', 5000);
        $this->expandExceptions = self::readBool($overrides, 'expandExceptions', false);
        $defaultShowContext = self::defaultShowContext();
        $this->showContext = self::readBool($overrides, 'showContext', $defaultShowContext);
        $this->showPerformanceMetrics = self::readBool($overrides, 'showPerformanceMetrics', false);
        $this->theme = self::readString($overrides, 'theme', 'auto');
        $this->stackLimit = self::readPositiveInt($overrides, 'stackLimit', 50);
        $this->messageLimit = self::readPositiveInt($overrides, 'messageLimit', 1000);
        $this->indentStyle = self::readString($overrides, 'indentStyle', 'spaces');
        $this->indentSize = self::readPositiveInt($overrides, 'indentSize', 2);

        $defaultRules = [
            RedactionRule::forPattern('/password/i'),
            RedactionRule::forPattern('/token/i'),
            RedactionRule::forPattern('/secret/i'),
        ];

        $redactionRules = self::extractRedactionRules($overrides['redactionRules'] ?? null);
        $this->redactionRules = $redactionRules !== [] ? $redactionRules : $defaultRules;

        $this->extras = $overrides;
        $this->extras['showTableVariableMeta'] = self::readBool($overrides, 'showTableVariableMeta', true);
        $this->extras['showPerformanceMetrics'] = $this->showPerformanceMetrics;
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    public function maxItems(): int
    {
        return $this->maxItems;
    }

    public function maxItemsHardLimit(): int
    {
        return $this->maxItemsHardLimit;
    }

    public function stringLengthLimit(): int
    {
        return $this->stringLengthLimit;
    }

    public function expandExceptions(): bool
    {
        return $this->expandExceptions;
    }

    public function showContext(): bool
    {
        return $this->showContext;
    }

    public function showPerformanceMetrics(): bool
    {
        return $this->showPerformanceMetrics;
    }

    public function theme(): string
    {
        return $this->theme;
    }

    /**
     * @return list<RedactionRule>
     */
    public function redactionRules(): array
    {
        return $this->redactionRules;
    }

    public function stackLimit(): int
    {
        return $this->stackLimit;
    }

    public function messageLimit(): int
    {
        return $this->messageLimit;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->extras[$key] ?? $default;
    }

    public function showTableVariableMeta(): bool
    {
        return (bool) ($this->extras['showTableVariableMeta'] ?? true);
    }

    public function indentStyle(): string
    {
        return $this->indentStyle;
    }

    public function indentSize(): int
    {
        return $this->indentSize;
    }

    /**
     * @param array<int|string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function sanitizeOverrides(array $overrides): array
    {
        $sanitized = [];

        foreach ($overrides as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function readPositiveInt(array $overrides, string $key, int $default): int
    {
        $value = $overrides[$key] ?? null;

        if (is_int($value)) {
            return max(1, $value);
        }

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function readBool(array $overrides, string $key, bool $default): bool
    {
        $value = $overrides[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return $default;
    }

    private static function defaultMaxDepth(?string $channel): int
    {
        // CLI defaults to 3 layers (limited screen space)
        // Web defaults to 5 layers (expandable UI)
        if ($channel === 'cli') {
            return 6;
        }

        if ($channel === 'web') {
            return 10;
        }

        // Fallback: detect context intelligently
        // Check for web context indicators (works with Workerman, Swoole, RoadRunner, etc.)
        if (in_array(PHP_SAPI, ['cgi', 'cgi-fcgi', 'fpm-fcgi'], true) ||
            !empty($_SERVER['REQUEST_METHOD']) ||
            !empty($_SERVER['HTTP_HOST']) ||
            !empty($_SERVER['REQUEST_URI'])) {
            return 10; // web
        }

        return 6; // cli
    }

    private static function defaultMaxItems(?string $channel): int
    {
        // CLI defaults to 500 items (increased for better debugging)
        // Web defaults to 5000 items (better scrolling and expandable UI)
        if ($channel === 'cli') {
            return 500;
        }

        if ($channel === 'web') {
            return 5000;
        }

        // Fallback: detect context intelligently
        // Check for web context indicators (works with Workerman, Swoole, RoadRunner, etc.)
        if (in_array(PHP_SAPI, ['cgi', 'cgi-fcgi', 'fpm-fcgi'], true) ||
            !empty($_SERVER['REQUEST_METHOD']) ||
            !empty($_SERVER['HTTP_HOST']) ||
            !empty($_SERVER['REQUEST_URI'])) {
            return 5000; // web
        }

        return 500; // cli
    }

    private static function defaultShowContext(): bool
    {
        $env = self::readEnvBool('PRETTY_DUMP_SHOW_CONTEXT');

        if ($env !== null) {
            return $env;
        }

        return !\in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    }

    private static function readEnvBool(string $key): ?bool
    {
        $value = getenv($key);

        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (bool) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function readString(array $overrides, string $key, string $default): string
    {
        $value = $overrides[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param mixed $candidate
     * @return list<RedactionRule>
     */
    private static function extractRedactionRules(mixed $candidate): array
    {
        if (!is_array($candidate)) {
            return [];
        }

        $rules = [];

        foreach ($candidate as $rule) {
            if ($rule instanceof RedactionRule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }
}
