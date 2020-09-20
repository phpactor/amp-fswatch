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
    private $includePatterns;

    /**
     * @var array<string>
     */
    private $excludePatterns;

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

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return sprintf('pattern matching %s', $this->innerWatcher->describe());
    }
}
