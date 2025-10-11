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
     */
    public function __construct(array $overrides = [])
    {
        $overrides = self::sanitizeOverrides($overrides);

        $this->maxDepth = self::readPositiveInt($overrides, 'maxDepth', 5);
        $this->maxItems = self::readPositiveInt($overrides, 'maxItems', 100);
        $this->maxItemsHardLimit = self::readPositiveInt($overrides, 'maxItemsHardLimit', 5000);
        $this->stringLengthLimit = self::readPositiveInt($overrides, 'stringLengthLimit', 160);
        $this->expandExceptions = self::readBool($overrides, 'expandExceptions', false);
        $this->showContext = self::readBool($overrides, 'showContext', true);
        $this->theme = self::readString($overrides, 'theme', 'auto');
        $this->stackLimit = self::readPositiveInt($overrides, 'stackLimit', 10);
        $this->messageLimit = self::readPositiveInt($overrides, 'messageLimit', 160);
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
