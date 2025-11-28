<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Formatter\Transformers;

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;

final class JsonTransformer
{
    public function matches(mixed $payload, DumpRenderRequest $request): bool
    {
        if (!is_string($payload)) {
            return false;
        }

        if ($request->option('autoDetectJson', false) !== true) {
            return false;
        }

        json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public function transform(string $payload, FormatterConfiguration $configuration): RenderedSegment
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $metadata = [
            'collapsible' => true,
            'preview' => $this->preview($payload, $configuration->stringLengthLimit()),
        ];

        $root = new RenderedSegment('json', 'JSON Document', $metadata);
        // Use JSON_UNESCAPED_UNICODE to preserve Chinese and other Unicode characters
        // Use JSON_UNESCAPED_SLASHES to avoid escaping forward slashes in URLs
        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $root->addChild(RenderedSegment::leaf('json-body', $encoded === false ? '' : $encoded));

        return $root;
    }

    private function preview(string $payload, int $limit): string
    {
        return strlen($payload) > $limit
            ? substr($payload, 0, $limit) . '…'
            : $payload;
    }
}
