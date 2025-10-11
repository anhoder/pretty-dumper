<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Support;

use InvalidArgumentException;

final class ThemeRegistry
{
    /**
     * @var array<string, ThemeProfile>
     */
    private array $profiles = [];

    private string $defaultCli;

    private string $defaultWeb;

    /**
     * @param list<ThemeProfile> $profiles
     */
    public function __construct(array $profiles, string $defaultCli, string $defaultWeb)
    {
        foreach ($profiles as $profile) {
            $this->profiles[$profile->name()] = $profile;
        }

        if (!isset($this->profiles[$defaultCli])) {
            throw new InvalidArgumentException(sprintf('Default CLI theme "%s" is not registered.', $defaultCli));
        }

        if (!isset($this->profiles[$defaultWeb])) {
            throw new InvalidArgumentException(sprintf('Default web theme "%s" is not registered.', $defaultWeb));
        }

        $this->defaultCli = $defaultCli;
        $this->defaultWeb = $defaultWeb;
    }

    public static function withDefaults(): self
    {
        $light = new ThemeProfile('light', [
            'background' => '#ffffff',
            'foreground' => '#1f2933',
            'accent' => '#2563eb',
        ], 4.6);

        $dark = new ThemeProfile('dark', [
            'background' => '#1f2933',
            'foreground' => '#f8fafc',
            'accent' => '#93c5fd',
        ], 7.1);

        return new self([$light, $dark], 'dark', 'light');
    }

    public function register(ThemeProfile $profile): void
    {
        $this->profiles[$profile->name()] = $profile;
    }

    public function get(string $name): ThemeProfile
    {
        if (!isset($this->profiles[$name])) {
            throw new InvalidArgumentException(sprintf('Theme "%s" is not registered.', $name));
        }

        return $this->profiles[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function defaultForChannel(string $channel): ThemeProfile
    {
        return $channel === 'cli'
            ? $this->profiles[$this->defaultCli]
            : $this->profiles[$this->defaultWeb];
    }

    /**
     * @return list<ThemeProfile>
     */
    public function all(): array
    {
        return array_values($this->profiles);
    }
}
