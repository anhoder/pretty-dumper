<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Support;

final class ThemeProfile
{
    /**
     * @param array<string, string> $palette
     * @param array<string> $assets
     */
    public function __construct(
        private string $name,
        private array $palette,
        private float $contrastRatio,
        private array $assets = [],
    ) {
        if ($contrastRatio < 4.5) {
            throw new \InvalidArgumentException('Theme contrast ratio must satisfy WCAG AA >= 4.5.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function palette(): array
    {
        return $this->palette;
    }

    public function contrastRatio(): float
    {
        return $this->contrastRatio;
    }

    /**
     * @return array<string>
     */
    public function assets(): array
    {
        return $this->assets;
    }
}
