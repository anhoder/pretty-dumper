<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Context\Collectors;

use Anhoder\PrettyDumper\Context\ContextSnapshot;
use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;

interface ContextCollector
{
    public function collect(DumpRenderRequest $request): ContextSnapshot;
}
