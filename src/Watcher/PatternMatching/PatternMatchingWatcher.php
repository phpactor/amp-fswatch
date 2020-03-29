<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Amp\Promise;
use Phpactor\AmpFsWatch\Watcher;

class PatternMatchingWatcher implements Watcher
{
    /**
     * @var Watcher
     */
    private $innerWatcher;

    /**
     * @var array<string>
     */
    private $patterns;

    /**
     * @param array<string> $patterns
     */
    public function __construct(Watcher $innerWatcher, array $patterns)
    {
        $this->innerWatcher = $innerWatcher;
        $this->patterns = $patterns;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $process = yield $this->innerWatcher->watch();
            return new PatternWatcherProcess($process, $this->patterns);
        });
    }

    public function isSupported(): Promise
    {
        return $this->innerWatcher->isSupported();
    }
}
