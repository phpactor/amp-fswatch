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
    public function watch(): Promise
    {
        return \Amp\call(function () {
            return $this;
        });
    }

    public function isSupported(): Promise
    {
        return new Success(true);
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

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'null';
    }
}
