<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\FsWatch;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class FsWatchWatcherTest extends WatcherTestCase
{
    /**
     * @var ObjectProphecy
     */
    private $commandDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('fswatch')->willReturn(true);
    }

    protected function createWatcher(): Watcher
    {
        return new FsWatchWatcher(
            $this->createLogger(),
            $this->commandDetector->reveal(),
        );
    }

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('fswatch')->willReturn(true);

        self::assertTrue($watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): void
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('fswatch')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }
}
