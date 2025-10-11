<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter;

final class RenderedSegment
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<RenderedSegment> $children
     */
    public function __construct(
        private string $type,
        private string $content,
        private array $metadata = [],
        private array $children = [],
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return list<RenderedSegment>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;

        return $clone;
    }

    public function withMetadata(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;

        return $clone;
    }

    public function addChild(RenderedSegment $segment): void
    {
        $this->children[] = $segment;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function appendContent(string $suffix): void
    {
        $this->content .= $suffix;
    }

    public function findChildByType(string $type): ?self
    {
        foreach ($this->children as $child) {
            if ($child->type() === $type) {
                return $child;
            }

            $nested = $child->findChildByType($type);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function leaf(string $type, string $content, array $metadata = []): self
    {
        return new self($type, $content, $metadata, []);
    }
}
