<?php

declare(strict_types=1);

namespace PrettyDumper\Formatter;

use InvalidArgumentException;
use PrettyDumper\Context\ContextSnapshot;

final class DumpRenderRequest
{
    private const ALLOWED_CHANNELS = ['cli', 'web'];

    private mixed $payload;

    private string $channel;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    private ?ContextSnapshot $context;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(mixed $payload, string $channel, array $options = [], ?ContextSnapshot $context = null)
    {
        if (!in_array($channel, self::ALLOWED_CHANNELS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported channel "%s"', $channel));
        }

        $this->payload = $payload;
        $this->channel = $channel;
        $this->options = self::sanitizeOptions($options);

        if ($context === null && isset($this->options['context']) && is_array($this->options['context'])) {
            $contextData = self::sanitizeOptions($this->options['context']);
            $context = ContextSnapshot::fromArray($contextData);
            unset($this->options['context']);
        }

        $this->context = $context;
    }

    public function &payload(): mixed
    {
        return $this->payload;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    public function context(): ?ContextSnapshot
    {
        return $this->context;
    }

    public function withOption(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->options[$key] = $value;

        return $clone;
    }

    public function withContext(ContextSnapshot $context): self
    {
        $clone = clone $this;
        $clone->context = $context;

        return $clone;
}

    /**
     * @param array<int|string, mixed> $options
     * @return array<string, mixed>
     */
    private static function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
