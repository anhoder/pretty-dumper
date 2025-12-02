<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter\Transformers;

use Anhoder\PrettyDumper\Formatter\RenderedSegment;

/**
 * DiffTransformer compares two values and generates a diff representation.
 */
class DiffTransformer
{
    public const DIFF_UNCHANGED = 'unchanged';
    public const DIFF_ADDED = 'added';
    public const DIFF_REMOVED = 'removed';
    public const DIFF_MODIFIED = 'modified';

    /**
     * Compare two values and return a diff structure.
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param int $depth Current recursion depth
     * @param int $maxDepth Maximum recursion depth
     * @return array{type: string, old: mixed, new: mixed, children?: array}
     */
    public function diff(mixed $oldValue, mixed $newValue, int $depth = 0, int $maxDepth = 10): array
    {
        if ($depth >= $maxDepth) {
            return [
                'type' => self::DIFF_UNCHANGED,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Identical values
        if ($oldValue === $newValue) {
            return [
                'type' => self::DIFF_UNCHANGED,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Different types
        if (gettype($oldValue) !== gettype($newValue)) {
            return [
                'type' => self::DIFF_MODIFIED,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Array/Object comparison
        if (is_array($oldValue) && is_array($newValue)) {
            return $this->diffArrays($oldValue, $newValue, $depth, $maxDepth);
        }

        if (is_object($oldValue) && is_object($newValue)) {
            return $this->diffObjects($oldValue, $newValue, $depth, $maxDepth);
        }

        // Scalar values that are different
        return [
            'type' => self::DIFF_MODIFIED,
            'old' => $oldValue,
            'new' => $newValue,
        ];
    }

    /**
     * Compare two arrays.
     */
    private function diffArrays(array $oldArray, array $newArray, int $depth, int $maxDepth): array
    {
        $children = [];
        $allKeys = array_unique(array_merge(array_keys($oldArray), array_keys($newArray)));

        foreach ($allKeys as $key) {
            $oldExists = array_key_exists($key, $oldArray);
            $newExists = array_key_exists($key, $newArray);

            if ($oldExists && $newExists) {
                $children[$key] = $this->diff($oldArray[$key], $newArray[$key], $depth + 1, $maxDepth);
            } elseif ($oldExists) {
                $children[$key] = [
                    'type' => self::DIFF_REMOVED,
                    'old' => $oldArray[$key],
                    'new' => null,
                ];
            } else {
                $children[$key] = [
                    'type' => self::DIFF_ADDED,
                    'old' => null,
                    'new' => $newArray[$key],
                ];
            }
        }

        return [
            'type' => $this->hasChanges($children) ? self::DIFF_MODIFIED : self::DIFF_UNCHANGED,
            'old' => $oldArray,
            'new' => $newArray,
            'children' => $children,
        ];
    }

    /**
     * Compare two objects.
     */
    private function diffObjects(object $oldObject, object $newObject, int $depth, int $maxDepth): array
    {
        // Different classes = modified
        if (get_class($oldObject) !== get_class($newObject)) {
            return [
                'type' => self::DIFF_MODIFIED,
                'old' => $oldObject,
                'new' => $newObject,
            ];
        }

        // Convert objects to arrays for comparison
        $oldArray = $this->objectToArray($oldObject);
        $newArray = $this->objectToArray($newObject);

        $result = $this->diffArrays($oldArray, $newArray, $depth, $maxDepth);
        $result['old'] = $oldObject;
        $result['new'] = $newObject;

        return $result;
    }

    /**
     * Convert object to associative array including private/protected properties.
     */
    private function objectToArray(object $object): array
    {
        $array = [];
        $reflection = new \ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $name = $property->getName();

            if (!$property->isInitialized($object)) {
                continue;
            }

            $array[$name] = $property->getValue($object);
        }

        return $array;
    }

    /**
     * Check if any children have changes.
     */
    private function hasChanges(array $children): bool
    {
        foreach ($children as $child) {
            if ($child['type'] !== self::DIFF_UNCHANGED) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a RenderedSegment tree from diff result.
     */
    public function createDiffSegment(array $diff, string $label = 'Diff'): RenderedSegment
    {
        $content = $this->getDiffContent($diff);
        $metadata = [
            'diffType' => $diff['type'],
            'label' => $label,
        ];

        $segment = new RenderedSegment('diff', $content, $metadata);

        if (isset($diff['children'])) {
            foreach ($diff['children'] as $key => $childDiff) {
                $childSegment = $this->createChildSegment($key, $childDiff);
                $segment->addChild($childSegment);
            }
        }

        return $segment;
    }

    private function createChildSegment(string|int $key, array $diff): RenderedSegment
    {
        $content = $this->getDiffContent($diff);
        $metadata = [
            'diffType' => $diff['type'],
            'key' => $key,
        ];

        $segment = new RenderedSegment('diff-item', $content, $metadata);

        if (isset($diff['children'])) {
            foreach ($diff['children'] as $childKey => $childDiff) {
                $childSegment = $this->createChildSegment($childKey, $childDiff);
                $segment->addChild($childSegment);
            }
        }

        return $segment;
    }

    private function getDiffContent(array $diff): string
    {
        switch ($diff['type']) {
            case self::DIFF_ADDED:
                return '+ ' . $this->formatValue($diff['new']);

            case self::DIFF_REMOVED:
                return '- ' . $this->formatValue($diff['old']);

            case self::DIFF_MODIFIED:
                $oldFormatted = $this->formatValue($diff['old']);
                $newFormatted = $this->formatValue($diff['new']);
                return "- {$oldFormatted}\n+ {$newFormatted}";

            case self::DIFF_UNCHANGED:
                return '  ' . $this->formatValue($diff['new']);

            default:
                return '';
        }
    }

    private function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            return '[' . count($value) . ' items]';
        }
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }
}
