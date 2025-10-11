<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Context\Collectors;

use Anhoder\PrettyDumper\Context\ContextFrame;
use Anhoder\PrettyDumper\Context\ContextSnapshot;
use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;

final class DefaultContextCollector implements ContextCollector
{
    #[\Override]
    public function collect(DumpRenderRequest $request): ContextSnapshot
    {
        /** @var list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $trace */
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        $libraryBase = realpath(dirname(__DIR__, 3)) ?: dirname(__DIR__, 3);
        $ignoredPrefixes = [
            strtolower(rtrim(str_replace('\\', '/', $libraryBase), '/') . '/'),
        ];

        $frames = [];

        foreach ($trace as $index => $frame) {
            if ($index === 0) {
                continue;
            }

            $file = $frame['file'] ?? 'unknown';

            if ($file !== 'unknown') {
                $normalizedFile = strtolower(str_replace('\\', '/', $file));
                $shouldSkip = false;

                foreach ($ignoredPrefixes as $prefix) {
                    if (str_starts_with($normalizedFile, $prefix)) {
                        $shouldSkip = true;
                        break;
                    }
                }

                if ($shouldSkip) {
                    continue;
                }
            }

            $line = isset($frame['line']) ? (int) $frame['line'] : 0;
            $function = $frame['function'] !== '' ? $frame['function'] : null;

            $frames[] = new ContextFrame(
                $file,
                $line,
                $function,
                [],
            );
        }

        $first = $frames[0] ?? new ContextFrame('unknown', 0, null, []);

        return new ContextSnapshot(
            origin: [
                'file' => $first->file(),
                'line' => $first->line(),
                'function' => $first->function(),
            ],
            stack: $frames,
            request: [],
            env: [],
            variables: [],
        );
    }
}
