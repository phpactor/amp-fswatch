<?php

namespace Phpactor\AmpFsWatch\Watcher\Null;

use Amp\Promise;
use Phpactor\AmpFsWatch\WatcherProcess;

use Phpactor\AmpFsWatch\Watcher;

class NullWatcher implements Watcher, WatcherProcess
{
    /**
     * {@inheritDoc}
     */
    public function watch(array $paths): Promise
    {
        return \Amp\call(function () {
            return $this;
        });
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
            return null;
        });
    }
}
