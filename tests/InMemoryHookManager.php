<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache\Tests;

use RyanHellyer\StaleCache\HookManager;

class InMemoryHookManager implements HookManager
{
    /** @var array<string, list<callable>> */
    private array $hooks = [];

    public function onShutdown(callable $callback): void
    {
        $this->hooks['shutdown'][] = $callback;
    }

    /** @return list<callable> */
    public function getShutdownCallbacks(): array
    {
        return $this->hooks['shutdown'] ?? [];
    }
}
