<?php

namespace Phpactor\AmpFsWatch\Watcher\TestWatcher;

use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;

class TestWatcher implements Watcher, WatcherProcess
{
    /**
     * @var ModifiedFileQueue
     */
    private $queue;

    public function __construct(ModifiedFileQueue $queue)
    {
        $this->queue = $queue;
    }

    public function watch(): Promise
    {
        return new Success($this);
    }

    public function isSupported(): Promise
    {
        return new Success(true);
    }

    public function stop(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): Promise
    {
        return \Amp\call(function () {
            while (null !== $file = $this->queue->dequeue()) {
                return $file;
            }

            return null;
        });
    }
}
