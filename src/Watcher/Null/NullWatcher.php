<?php

namespace Phpactor\AmpFsWatch\Watcher\Null;

use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\WatcherProcess;

use Phpactor\AmpFsWatch\Watcher;

class NullWatcher implements Watcher, WatcherProcess
{
    /**
     * {@inheritDoc}
     */
    public function watch(array $paths): WatcherProcess
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

    public function wait(): Promise
    {
        return \Amp\call(function () {
            return new Success(null);
        });
    }
}
