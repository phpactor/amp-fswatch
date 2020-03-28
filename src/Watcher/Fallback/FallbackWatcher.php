<?php

namespace Phpactor\AmpFsWatch\Watcher\Fallback;

use Amp\Promise;
use Phpactor\AmpFsWatch\Watcher;
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

    public function watch(array $paths): Promise
    {
        return \Amp\call(function () use ($paths) {
            $watcherClasses = [];
            foreach ($this->watchers as $watcher) {
                $watcherClasses[] = get_class($watcher);

                if (!$watcher->isSupported()) {
                    continue;
                }

                $this->logger->notice(sprintf(
                    'Watching files with "%s"',
                    get_class($watcher)
                ));

                return $watcher->watch($paths);
            }

            $this->logger->warning(sprintf(
                'No supported watchers, tried "%s".',
                implode('", "', $watcherClasses)
            ));

            return new NullWatcher();
        });
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
