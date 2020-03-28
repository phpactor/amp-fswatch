<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\FsWatch;

use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
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

    protected function createWatcher(?array $paths = null): Watcher
    {
        return new FsWatchWatcher(
            new WatcherConfig($paths ?? [
                $this->workspace()->path()
            ]),
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('fswatch')->willReturn(new Success(true));

        self::assertTrue(yield $watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): Generator
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('fswatch')->willReturn(new Success(false));
        self::assertFalse(yield $watcher->isSupported());
    }
}
