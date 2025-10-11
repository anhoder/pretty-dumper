<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter\Support;

final class PerformanceMonitor
{
    private ?float $startedAt = null;

    private ?float $durationMs = null;

    private int $truncatedCount = 0;

    public function start(): void
    {
        $this->startedAt = hrtime(true);
        $this->durationMs = null;
        $this->truncatedCount = 0;
    }

    public function stop(): void
    {
        if ($this->startedAt === null) {
            return;
        }

        $elapsedNs = hrtime(true) - $this->startedAt;
        $this->durationMs = $elapsedNs / 1_000_000;
    }

    public function registerTruncation(): void
    {
        $this->truncatedCount++;
    }

    public function durationMs(): float
    {
        return $this->durationMs ?? 0.0;
    }

    public function truncatedCount(): int
    {
        return $this->truncatedCount;
    }
}
