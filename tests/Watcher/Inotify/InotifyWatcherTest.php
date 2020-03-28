<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class InotifyWatcherTest extends WatcherTestCase
{
    /**
     * @var ObjectProphecy
     */
    private $commandDetector;

    /**
     * @var ObjectProphecy
     */
    private $osValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);
        $this->osValidator = $this->prophesize(OsDetector::class);
        $this->osValidator->isLinux()->willReturn(true);
    }

    protected function createWatcher(?array $paths = null): Watcher
    {
        return new InotifyWatcher(
            new WatcherConfig($paths ?? [
                $this->workspace()->path()
            ]),
            $this->createLogger(),
            $this->commandDetector->reveal(),
            $this->osValidator->reveal()
        );
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(true));

        self::assertTrue(yield $watcher->isSupported());
    }

    public function testNotSupportedOnNonLinux(): Generator
    {
        $watcher = $this->createWatcher();
        $this->osValidator->isLinux()->willReturn(false);
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(true));
        self::assertFalse(yield $watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): Generator
    {
        $watcher = $this->createWatcher();
        $this->osValidator->isLinux()->willReturn(true);
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(false));
        self::assertFalse(yield $watcher->isSupported());
    }
}
