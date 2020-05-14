<?php

namespace Phpactor\AmpFsWatch\Watcher\BufferedWatcher;

use Amp\Promise;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\WatcherProcess;

class BufferedWatcherProcess implements WatcherProcess
{
    /**
     * @var WatcherProcess
     */
    private $innerProcess;

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var array<string,ModifiedFile>
     */
    private $buffer = [];

    /**
     * @var int
     */
    private $interval;

    /**
     * @var float
     */
    private $startTime;

    public function __construct(WatcherProcess $innerProcess, int $interval = 500)
    {
        $this->innerProcess = $innerProcess;
        $this->interval = $interval;
    }

    public function stop(): void
    {
        $this->running = false;
        $this->innerProcess->stop();
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): Promise
    {
        return \Amp\call(function () {
            if (!empty($this->buffer)) {
                return array_shift($this->buffer);
            }

            if (false === $this->running) {
                return null;
            }

            $start = $this->milliseconds();
            while (null !== $modifiedFile = yield $this->innerProcess->wait()) {
                assert($modifiedFile instanceof ModifiedFile);
                $this->buffer[$modifiedFile->path()] = $modifiedFile;

                if ($this->milliseconds() - $start >= $this->interval) {
                    return yield $this->wait();
                }
            }

            $this->running = false;

            return yield $this->wait();
        });
    }

    private function milliseconds(): int
    {
        return (int)microtime(true) * 1000;
    }
}
