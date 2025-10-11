<?php

declare(strict_types=1);

namespace PrettyDumper\Context\Collectors;

use PrettyDumper\Context\ContextFrame;
use PrettyDumper\Context\ContextSnapshot;
use PrettyDumper\Formatter\DumpRenderRequest;

interface ContextCollector
{
    public function collect(DumpRenderRequest $request): ContextSnapshot;
}
