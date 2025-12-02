<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Storage;

/**
 * Storage for dump history to enable auto-diff functionality.
 * Supports multiple storage backends (memory, file, custom).
 */
class DumpHistoryStorage
{
    private static ?StorageInterface $storage = null;

    /**
     * Set custom storage backend.
     */
    public static function setStorage(StorageInterface $storage): void
    {
        self::$storage = $storage;
    }

    /**
     * Get the current storage backend.
     */
    public static function getStorage(): StorageInterface
    {
        if (self::$storage === null) {
            // Default to memory storage
            self::$storage = new MemoryStorage();
        }

        return self::$storage;
    }

    /**
     * Use file-based persistent storage.
     *
     * @param string $storageDir Directory to store history files (default: system temp dir)
     * @param int $maxEntriesPerLocation Maximum entries per location (default: 10)
     * @param int $ttl Time to live in seconds (default: 3600 = 1 hour, 0 = forever)
     */
    public static function usePersistentStorage(
        string $storageDir = '',
        int $maxEntriesPerLocation = 10,
        int $ttl = 3600
    ): void {
        self::$storage = new FileStorage($storageDir, $maxEntriesPerLocation, $ttl);
    }

    /**
     * Use in-memory storage (default, not persistent).
     */
    public static function useMemoryStorage(int $maxEntriesPerLocation = 10): void
    {
        self::$storage = new MemoryStorage($maxEntriesPerLocation);
    }

    /**
     * Store a value at a specific location.
     */
    public static function store(string $location, mixed $value): void
    {
        self::getStorage()->store($location, $value);
    }

    /**
     * Get the last stored value for a location.
     */
    public static function getLast(string $location): mixed
    {
        return self::getStorage()->getLast($location);
    }

    /**
     * Get all history for a location.
     *
     * @return array<int, array{value: mixed, timestamp: float}>
     */
    public static function getHistory(string $location): array
    {
        return self::getStorage()->getHistory($location);
    }

    /**
     * Check if a location has history.
     */
    public static function hasHistory(string $location): bool
    {
        return self::getStorage()->hasHistory($location);
    }

    /**
     * Clear history for a specific location.
     */
    public static function clear(string $location): void
    {
        self::getStorage()->clear($location);
    }

    /**
     * Clear all history.
     */
    public static function clearAll(): void
    {
        self::getStorage()->clearAll();
    }

    /**
     * Generate a location identifier from backtrace.
     */
    public static function generateLocation(array $trace): string
    {
        // Find the first frame outside of PrettyDumper and helpers.php
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file && !str_contains($file, 'PrettyDumper') && !str_ends_with($file, 'helpers.php')) {
                $line = $frame['line'] ?? 0;
                return "{$file}:{$line}";
            }
        }

        // If no suitable frame found, return the first available frame
        if (!empty($trace)) {
            $firstFrame = $trace[0];
            $file = $firstFrame['file'] ?? 'unknown';
            $line = $firstFrame['line'] ?? 0;
            return "{$file}:{$line}";
        }

        return 'unknown';
    }

    /**
     * Set maximum entries to keep per location.
     * This recreates the storage backend with new settings.
     */
    public static function setMaxEntriesPerLocation(int $max): void
    {
        $max = max(1, $max);
        $storage = self::getStorage();

        if ($storage instanceof MemoryStorage) {
            self::$storage = new MemoryStorage($max);
        } elseif ($storage instanceof FileStorage) {
            // For file storage, recreate with same settings but new max
            $newStorage = new FileStorage('', $max);
            self::$storage = $newStorage;
        }
    }
}
