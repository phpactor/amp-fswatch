<?php

namespace Phpactor\AmpFsWatch\Watcher\Fallback;

use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Psr\Log\LoggerInterface;

class FallbackWatcher implements Watcher
{
    /**
     * @var array<Watcher>
     */
    private $watchers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param array<Watcher> $watchers
     */
    public function __construct(array $watchers, LoggerInterface $logger)
    {
        $this->logger = $logger;
        foreach ($watchers as $watcher) {
            $this->add($watcher);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function watch(array $paths, callable $callback): WatcherProcess
    {
        $watcherClasses = [];
        foreach ($this->watchers as $watcher) {
            $watcherClasses[] = get_class($watcher);
            if (!$watcher->isSupported()) {
                continue;
            }

            return $watcher->watch($paths, $callback);
        }

        $this->logger->warning(sprintf(
            'Could not find a file watching strategy, tried "%s" not watching any files',
            implode('", "', $watcherClasses)
        ));

        return new NullWatcher();
    }

    public function isSupported(): bool
    {
        return true;
    }

    private function add(Watcher $watcher): void
    {
        $this->watchers[] = $watcher;
    }
}
