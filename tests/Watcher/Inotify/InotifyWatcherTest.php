<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\Watcher;
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

    protected function createWatcher(): Watcher
    {
        return new InotifyWatcher(
            $this->createLogger(),
            $this->commandDetector->reveal(),
            $this->osValidator->reveal()
        );
    }

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher();
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);

        self::assertTrue($watcher->isSupported());
    }

    public function testNotSupportedOnNonLinux(): void
    {
        $watcher = $this->createWatcher();
        $this->osValidator->isLinux()->willReturn(false);
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);
        self::assertFalse($watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): void
    {
        $watcher = $this->createWatcher();
        $this->osValidator->isLinux()->willReturn(true);
        $this->commandDetector->commandExists('inotifywait')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }
}
