<?php

namespace Phpactor\AmpFsWatch\Watcher\Filtering;

use Amp\Promise;
use Phpactor\AmpFsWatch\Watcher;

class PatternFilteringWatcher implements Watcher
{
    /**
     * @var Watcher
     */
    private $innerWatcher;

    /**
     * @var string
     */
    private $pattern;

    public function __construct(Watcher $innerWatcher, string $pattern)
    {
        $this->innerWatcher = $innerWatcher;
        $this->pattern = $pattern;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $process = yield $this->innerWatcher->watch();
            return new FilteringWatcherProcess($process, $this->pattern);
        });
    }

    public function isSupported(): Promise
    {
        return $this->innerWatcher->isSupported();
    }
}
