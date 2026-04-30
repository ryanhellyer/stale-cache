<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

class WordPressHookManager implements HookManager
{
    public function onShutdown(callable $callback): void
    {
        add_action('shutdown', $callback); // @phpstan-ignore-line
    }
}
