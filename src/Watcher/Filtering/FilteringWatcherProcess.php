<?php

namespace Phpactor\AmpFsWatch\Watcher\Filtering;

use Amp\Promise;
use Phpactor\AmpFsWatch\WatcherProcess;

class FilteringWatcherProcess implements WatcherProcess
{
    /**
     * @var WatcherProcess
     */
    private $process;

    /**
     * @var string
     */
    private $regexPattern;

    /**
     * @var string
     */
    private $pattern;

    /**
     * @var PatternMatcher
     */
    private $matcher;

    public function __construct(WatcherProcess $process, string $pattern, ?PatternMatcher $matcher = null)
    {
        $this->process = $process;
        $this->regexPattern = $pattern;
        $this->pattern = $pattern;
        $this->matcher = $matcher ?: new PatternMatcher();
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
                if (false === $this->matcher->matches($file->path(), $this->pattern)) {
                    continue;
                }

                return $file;
            }
        });
    }
}
