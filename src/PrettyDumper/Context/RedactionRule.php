<?php

declare(strict_types=1);

namespace PrettyDumper\Context;

final class RedactionRule
{
    public const SCOPE_ANY = 'any';
    public const SCOPE_REQUEST = 'request';
    public const SCOPE_ENV = 'env';
    public const SCOPE_PAYLOAD = 'payload';

    public function __construct(
        private string $pattern,
        private string $replacement = '***',
        private string $scope = self::SCOPE_ANY,
    ) {
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public static function forPattern(string $pattern, string $replacement = '***', string $scope = self::SCOPE_ANY): self
    {
        return new self($pattern, $replacement, $scope);
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function replacement(): string
    {
        return $this->replacement;
    }

    public function matches(string $key, string $scope = self::SCOPE_ANY): bool
    {
        if ($this->scope !== self::SCOPE_ANY && $this->scope !== $scope) {
            return false;
        }

        if ($this->pattern !== '' && $this->pattern[0] === '/') {
            $result = preg_match($this->pattern, $key);

            if ($result === false) {
                return false;
            }

            return $result === 1;
        }

        return strcasecmp($this->pattern, $key) === 0;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    public function applyToArray(array $data, string $scope = self::SCOPE_ANY): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->matches($key, $scope)) {
                $data[$key] = $this->replacement;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->applyToArray($value, $scope);
            }
        }

        return $data;
    }
}
