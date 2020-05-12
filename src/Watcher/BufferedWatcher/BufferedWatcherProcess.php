<?php

namespace Phpactor\AmpFsWatch\Watcher\BufferedWatcher;

use Amp\Delayed;
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
     * @var array<ModifiedFile>
     */
    private $buffer = [];

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var int
     */
    private $interval;

    public function __construct(WatcherProcess $innerProcess, int $interval = 500)
    {
        $this->innerProcess = $innerProcess;
        $this->interval = $interval;

        \Amp\asyncCall(function () {
            while ($modifiedFile = yield $this->innerProcess->wait()) {
                assert($modifiedFile instanceof ModifiedFile);
                $this->buffer[$modifiedFile->path()] = $modifiedFile;
            }
        });
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
            while ($this->running) {
                if ($this->buffer) {
                    return array_shift($this->buffer);
                }
                yield new Delayed($this->interval);
            }
        });
    }
}
