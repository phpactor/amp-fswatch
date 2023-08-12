<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class FindWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    private const PLAN_DELAY = 100;

    /**
     * @var ObjectProphecy<CommandDetector>
     */
    private ObjectProphecy $commandDetector;

    public function testRemoval(): Generator
    {
        $this->markTestSkipped('Not supported');
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue(yield $watcher->isSupported());
    }

    public function testIsNotSupportedIfFindNotFound(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('find')->willReturn(new Success(false));
        self::assertFalse(yield $watcher->isSupported());
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('find')->willReturn(new Success(true));

        return new FindWatcher(
            $config->withPollInterval(100),
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }
}
