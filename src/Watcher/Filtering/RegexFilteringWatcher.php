<?php

namespace Phpactor\AmpFsWatch\Watcher\Filtering;

use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RegexFilteringWatcher implements Watcher
{
    /**
     * @var Watcher
     */
    private $innerWatcher;

    /**
     * @var string
     */
    private $regexPattern;

    public function __construct(Watcher $innerWatcher, string $regexPattern)
    {
        $this->innerWatcher = $innerWatcher;
        $this->regexPattern = $regexPattern;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $process = yield $this->innerWatcher->watch();
            return new FilteringWatcherProcess($process, $this->regexPattern);
        });
    }

    public function isSupported(): Promise
    {
        return $this->innerWatcher->isSupported();
    }
}

