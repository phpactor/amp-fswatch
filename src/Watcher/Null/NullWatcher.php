<?php

namespace Phpactor\AmpFsWatch\Watcher\Null;

use Phpactor\AmpFsWatch\WatcherProcess;

use Phpactor\AmpFsWatch\Watcher;

class NullWatcher implements Watcher, WatcherProcess
{
    /**
     * {@inheritDoc}
     */
    public function watch(array $paths, callable $callback): WatcherProcess
    {
        return $this;
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function stop(): void
    {
    }
}
