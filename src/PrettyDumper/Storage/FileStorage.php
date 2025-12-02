<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Storage;

/**
 * File-based storage backend (persistent across requests).
 */
class FileStorage implements StorageInterface
{
    private string $storageDir;
    private int $maxEntriesPerLocation;
    private int $ttl; // Time to live in seconds (0 = forever)

    /**
     * @param string $storageDir Directory to store history files
     * @param int $maxEntriesPerLocation Maximum entries per location
     * @param int $ttl Time to live in seconds (0 = forever, default: 3600 = 1 hour)
     */
    public function __construct(
        string $storageDir = '',
        int $maxEntriesPerLocation = 10,
        int $ttl = 3600
    ) {
        if ($storageDir === '') {
            $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pretty-dumper';
        }

        $this->storageDir = rtrim($storageDir, DIRECTORY_SEPARATOR);
        $this->maxEntriesPerLocation = max(1, $maxEntriesPerLocation);
        $this->ttl = $ttl;

        $this->ensureStorageDirectory();
    }

    public function store(string $location, mixed $value): void
    {
        $this->ensureStorageDirectory();

        $filePath = $this->getFilePath($location);
        $history = $this->loadHistory($filePath);

        $history[] = [
            'value' => $value,
            'timestamp' => microtime(true),
        ];

        // Limit history size
        if (count($history) > $this->maxEntriesPerLocation) {
            $history = array_slice($history, -$this->maxEntriesPerLocation);
        }

        $this->saveHistory($filePath, $history);
    }

    public function getLast(string $location): mixed
    {
        $history = $this->getHistory($location);

        if (empty($history)) {
            return null;
        }

        $last = end($history);
        return $last['value'] ?? null;
    }

    public function getHistory(string $location): array
    {
        $filePath = $this->getFilePath($location);

        if (!file_exists($filePath)) {
            return [];
        }

        // Check TTL
        if ($this->ttl > 0 && (time() - filemtime($filePath)) > $this->ttl) {
            $this->clear($location);
            return [];
        }

        return $this->loadHistory($filePath);
    }

    public function hasHistory(string $location): bool
    {
        return !empty($this->getHistory($location));
    }

    public function clear(string $location): void
    {
        $filePath = $this->getFilePath($location);

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function clearAll(): void
    {
        if (!is_dir($this->storageDir)) {
            return;
        }

        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Clean up expired entries (useful for cron jobs).
     */
    public function cleanExpired(): int
    {
        if (!is_dir($this->storageDir) || $this->ttl === 0) {
            return 0;
        }

        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return 0;
        }

        $cleaned = 0;
        $now = time();

        foreach ($files as $file) {
            if (($now - filemtime($file)) > $this->ttl) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    private function getFilePath(string $location): string
    {
        // Create a safe filename from location
        $hash = md5($location);
        return $this->storageDir . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function loadHistory(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = @file_get_contents($filePath);

        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    private function saveHistory(string $filePath, array $history): void
    {
        $json = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            return;
        }

        @file_put_contents($filePath, $json, LOCK_EX);
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Get storage directory path.
     */
    public function getStorageDir(): string
    {
        return $this->storageDir;
    }

    /**
     * Get number of stored locations.
     */
    public function getLocationCount(): int
    {
        if (!is_dir($this->storageDir)) {
            return 0;
        }

        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json');

        return $files === false ? 0 : count($files);
    }
}
