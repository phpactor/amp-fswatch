<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Amp\Promise;
use Phpactor\AmpFsWatch\WatcherProcess;

class PatternWatcherProcess implements WatcherProcess
{
    /**
     * @var WatcherProcess
     */
    private $process;

    /**
     * @var array<string>
     */
    private $includePatterns;

    /**
     * @var PatternMatcher
     */
    private $matcher;

    /**
     * @param array<string> $patterns
     */
    public function __construct(WatcherProcess $process, array $includePatterns, ?PatternMatcher $matcher = null)
    {
        $this->process = $process;
        $this->matcher = $matcher ?: new PatternMatcher();
        $this->includePatterns = $includePatterns;
    }

    public function stop(): void
    {
        $this->process->stop();
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): Promise
    {
        return \Amp\call(function () {
            while (null !== $file = yield $this->process->wait()) {
                foreach ($this->includePatterns as $pattern) {
                    if (false === $this->matcher->matches($file->path(), $pattern)) {
                        continue 2;
                    }

                    return $file;
                }
            }
        });
    }
}
