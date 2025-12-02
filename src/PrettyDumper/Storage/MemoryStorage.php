<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Storage;

/**
 * In-memory storage backend (default, fast but not persistent).
 */
class MemoryStorage implements StorageInterface
{
    /** @var array<string, array<int, array{value: mixed, timestamp: float}>> */
    private array $history = [];

    private int $maxEntriesPerLocation = 10;

    public function __construct(int $maxEntriesPerLocation = 10)
    {
        $this->maxEntriesPerLocation = max(1, $maxEntriesPerLocation);
    }

    public function store(string $location, mixed $value): void
    {
        if (!isset($this->history[$location])) {
            $this->history[$location] = [];
        }

        $this->history[$location][] = [
            'value' => $this->cloneValue($value),
            'timestamp' => microtime(true),
        ];

        if (count($this->history[$location]) > $this->maxEntriesPerLocation) {
            array_shift($this->history[$location]);
        }
    }

    public function getLast(string $location): mixed
    {
        if (!isset($this->history[$location]) || empty($this->history[$location])) {
            return null;
        }

        $last = end($this->history[$location]);
        return $last['value'] ?? null;
    }

    public function getHistory(string $location): array
    {
        return $this->history[$location] ?? [];
    }

    public function hasHistory(string $location): bool
    {
        return isset($this->history[$location]) && !empty($this->history[$location]);
    }

    public function clear(string $location): void
    {
        unset($this->history[$location]);
    }

    public function clearAll(): void
    {
        $this->history = [];
    }

    private function cloneValue(mixed $value): mixed
    {
        if (is_object($value)) {
            try {
                return clone $value;
            } catch (\Throwable) {
                try {
                    return unserialize(serialize($value));
                } catch (\Throwable) {
                    return get_class($value) . ' (non-cloneable)';
                }
            }
        }

        if (is_array($value)) {
            $cloned = [];
            foreach ($value as $key => $item) {
                $cloned[$key] = $this->cloneValue($item);
            }
            return $cloned;
        }

        return $value;
    }
}
