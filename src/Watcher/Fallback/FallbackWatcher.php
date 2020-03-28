<?php

namespace Phpactor\AmpFsWatch\Watcher\Fallback;

use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
    public function __construct(array $watchers, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
        foreach ($watchers as $watcher) {
            $this->add($watcher);
        }
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $watcherClasses = [];
            foreach ($this->watchers as $watcher) {
                $watcherClasses[] = get_class($watcher);

                if (!yield $watcher->isSupported()) {
                    continue;
                }

                $this->logger->notice(sprintf(
                    'Watching files with "%s"',
                    get_class($watcher)
                ));

                return $watcher->watch();
            }

            $this->logger->warning(sprintf(
                'No supported watchers, tried "%s".',
                implode('", "', $watcherClasses)
            ));

            return new NullWatcher();
        });
    }

    public function isSupported(): Promise
    {
        return new Success(true);
    }

    private function add(Watcher $watcher): void
    {
        $this->watchers[] = $watcher;
    }
}
