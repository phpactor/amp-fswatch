<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class FindWatcherTest extends WatcherTestCase
{
    private const PLAN_DELAY = 100;

    /**
     * @var ObjectProphecy
     */
    private $commandDetector;

    protected function createWatcher(): Watcher
    {
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('find')->willReturn(new Success(true));

        return new FindWatcher(
            100,
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }

    public function testRemoval(): Generator
    {
        $this->markTestSkipped('Not supported');
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher();
        self::assertTrue(yield $watcher->isSupported());
    }

    public function testIsNotSupportedIfFindNotFound(): Generator
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('find')->willReturn(new Success(false));
        self::assertFalse(yield $watcher->isSupported());
    }
}
