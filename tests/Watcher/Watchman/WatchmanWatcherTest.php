<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Watchman;

use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Watchman\WatchmanWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class WatchmanWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    private const PLAN_DELAY = 100;

    /**
     * @var ObjectProphecy|CommandDetector
     */
    private $commandDetector;

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue(yield $watcher->isSupported());
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('watchman')->willReturn(new Success(true));

        return new WatchmanWatcher(
            $config->withPollInterval(100),
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }
}
