<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Storage;

/**
 * Interface for dump history storage backends.
 */
interface StorageInterface
{
    /**
     * Store a value at a specific location.
     */
    public function store(string $location, mixed $value): void;

    /**
     * Get the last stored value for a location.
     */
    public function getLast(string $location): mixed;

    /**
     * Get all history for a location.
     *
     * @return array<int, array{value: mixed, timestamp: float}>
     */
    public function getHistory(string $location): array;

    /**
     * Check if a location has history.
     */
    public function hasHistory(string $location): bool;

    /**
     * Clear history for a specific location.
     */
    public function clear(string $location): void;

    /**
     * Clear all history.
     */
    public function clearAll(): void;
}
