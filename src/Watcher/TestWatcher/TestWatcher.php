<?php

namespace Phpactor\AmpFsWatch\Watcher\TestWatcher;

use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Exception;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;

class TestWatcher implements Watcher, WatcherProcess
{
    /**
     * @var ModifiedFileQueue
     */
    private $queue;

    /**
     * @var int
     */
    private $delay;

    /**
     * @var Exception|null
     */
    private $error;

    public function __construct(ModifiedFileQueue $queue, int $delay = 0, ?Exception $error = null)
    {
        $this->queue = $queue;
        $this->delay = $delay;
        $this->error = $error;
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
            if ($this->delay) {
                yield new Delayed($this->delay);
            }

            if ($this->error) {
                throw $this->error;
            }

            while (null !== $file = $this->queue->dequeue()) {
                return $file;
            }

            return null;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'test';
    }
}
