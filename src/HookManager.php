<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

interface HookManager
{
    public function onShutdown(callable $callback): void;
}
