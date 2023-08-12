<?php

namespace Phpactor\AmpFsWatch\Watcher\BufferedWatcher;

use Amp\Promise;
use Phpactor\AmpFsWatch\Watcher;

class BufferedWatcher implements Watcher
{
    private Watcher $innerWatcher;

    private int $interval;

    public function __construct(Watcher $innerWatcher, int $interval)
    {
        $this->innerWatcher = $innerWatcher;
        $this->interval = $interval;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            return new BufferedWatcherProcess(yield $this->innerWatcher->watch(), $this->interval);
        });
    }


    public function isSupported(): Promise
    {
        return $this->innerWatcher->isSupported();
    }


    public function describe(): string
    {
        return sprintf('buffered %s', $this->innerWatcher->describe());
    }
}
