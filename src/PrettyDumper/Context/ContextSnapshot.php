<?php

declare(strict_types=1);

namespace PrettyDumper\Context;

final class ContextSnapshot
{
    /**
     * @param array{file: string, line: int, function?: ?string} $origin
     * @param list<ContextFrame> $stack
     * @param array<string, mixed> $request
     * @param array<string, mixed> $env
     * @param array<string, mixed> $variables
     */
    public function __construct(
        private array $origin,
        private array $stack,
        private array $request,
        private array $env,
        private array $variables = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $originData = $data['origin'] ?? null;
        $origin = is_array($originData) ? self::ensureStringArray($originData) : [];

        $stack = [];
        if (isset($data['stack']) && is_array($data['stack'])) {
            foreach ($data['stack'] as $frame) {
                if (!is_array($frame)) {
                    continue;
                }

                $stack[] = ContextFrame::fromArray(self::ensureStringArray($frame));
            }
        }

        $request = isset($data['request']) && is_array($data['request']) ? self::ensureStringArray($data['request']) : [];
        $env = isset($data['env']) && is_array($data['env']) ? self::ensureStringArray($data['env']) : [];
        $variables = isset($data['variables']) && is_array($data['variables']) ? self::ensureStringArray($data['variables']) : [];

        $originFile = isset($origin['file']) && is_string($origin['file']) ? $origin['file'] : 'unknown';
        $originLine = isset($origin['line']) && is_numeric($origin['line']) ? (int) $origin['line'] : 0;
        $originFunction = array_key_exists('function', $origin) && (is_string($origin['function']) || $origin['function'] === null)
            ? $origin['function']
            : null;

        return new self(
            [
                'file' => $originFile,
                'line' => $originLine,
                'function' => $originFunction,
            ],
            $stack,
            $request,
            $env,
            $variables,
        );
    }

    /**
     * @return array{file: string, line: int, function?: ?string}
     */
    public function origin(): array
    {
        return $this->origin;
    }

    /**
     * @return list<ContextFrame>
     */
    public function stack(): array
    {
        return $this->stack;
    }

    /**
     * @return array<string, mixed>
     */
    public function request(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function env(): array
    {
        return $this->env;
    }

    /**
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return $this->variables;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'stack' => array_map(static fn (ContextFrame $frame): array => $frame->toArray(), $this->stack),
            'request' => $this->request,
            'env' => $this->env,
            'variables' => $this->variables,
        ];
    }

    /**
     * @param list<ContextFrame> $stack
     */
    public function withStack(array $stack): self
    {
        $clone = clone $this;
        $clone->stack = $stack;

        return $clone;
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $env
     * @param array<string, mixed> $variables
     */
    public function withSanitizedData(array $request, array $env, array $variables): self
    {
        $clone = clone $this;
        $clone->request = $request;
        $clone->env = $env;
        $clone->variables = $variables;

        return $clone;
    }

    /**
     * @param array<mixed, mixed> $array
     * @return array<string, mixed>
    */
    private static function ensureStringArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
