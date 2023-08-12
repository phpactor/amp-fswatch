<?php

namespace Phpactor\AmpFsWatch\Watcher\BufferedWatcher;

use Amp\Delayed;
use Amp\Promise;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\WatcherProcess;
use Throwable;

class BufferedWatcherProcess implements WatcherProcess
{
    private WatcherProcess $innerProcess;

    /**
     * @var array<ModifiedFile>
     */
    private array $buffer = [];

    private bool $running = true;

    private int $interval;

    private ?Throwable $error = null;

    public function __construct(WatcherProcess $innerProcess, int $interval = 500)
    {
        $this->innerProcess = $innerProcess;
        $this->interval = $interval;

        \Amp\asyncCall(function () {
            try {
                while (null !== $modifiedFile = yield $this->innerProcess->wait()) {
                    assert($modifiedFile instanceof ModifiedFile);
                    $this->buffer[$modifiedFile->path()] = $modifiedFile;
                }
            } catch (Throwable $error) {
                $this->error = $error;
            }
            $this->running = false;
        });
    }

    public function stop(): void
    {
        $this->running = false;
        $this->innerProcess->stop();
    }


    public function wait(): Promise
    {
        return \Amp\call(function () {
            while ($this->running || !empty($this->buffer || null !== $this->error)) {
                if ($this->error) {
                    $error = $this->error;
                    $this->error = null;
                    throw $error;
                }
                if ($this->buffer) {
                    return array_shift($this->buffer);
                }
                yield new Delayed($this->interval);
            }

            return null;
        });
    }
}
