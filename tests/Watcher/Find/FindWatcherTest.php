<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

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
        $this->commandDetector->commandExists('find')->willReturn(true);
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

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher();
        self::assertTrue($watcher->isSupported());
    }

    public function testIsNotSupportedIfFindNotFound(): void
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('find')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }
}
