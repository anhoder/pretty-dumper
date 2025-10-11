<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Context;

final class ContextFrame
{
    /**
     * @param array<int|string, mixed> $args
     */
    public function __construct(
        private string $file,
        private int $line,
        private ?string $function = null,
        private array $args = [],
    ) {
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function function(): ?string
    {
        return $this->function;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $file = isset($data['file']) && is_string($data['file']) ? $data['file'] : 'unknown';
        $line = isset($data['line']) && is_numeric($data['line']) ? (int) $data['line'] : 0;
        $function = isset($data['function']) && is_string($data['function']) ? $data['function'] : null;
        $args = is_array($data['args'] ?? null) ? $data['args'] : [];

        return new self($file, $line, $function, $args);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'function' => $this->function,
            'args' => $this->args,
        ];
    }
}
