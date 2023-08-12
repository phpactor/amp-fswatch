<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Amp\Promise;
use Phpactor\AmpFsWatch\Watcher;

class PatternMatchingWatcher implements Watcher
{
    private Watcher $innerWatcher;

    /**
     * @var array<string>
     */
    private array $includePatterns;

    /**
     * @var array<string>
     */
    private array $excludePatterns;

    /**
     * @param array<string> $includePatterns
     * @param array<string> $excludePatterns
     */
    public function __construct(Watcher $innerWatcher, array $includePatterns, array $excludePatterns)
    {
        $this->innerWatcher = $innerWatcher;
        $this->includePatterns = $includePatterns;
        $this->excludePatterns = $excludePatterns;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $process = yield $this->innerWatcher->watch();
            return new PatternWatcherProcess($process, $this->includePatterns, $this->excludePatterns);
        });
    }

    public function isSupported(): Promise
    {
        return $this->innerWatcher->isSupported();
    }


    public function describe(): string
    {
        return sprintf('pattern matching %s', $this->innerWatcher->describe());
    }
}
