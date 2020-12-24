<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Amp\Delayed;
use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\PathUtil\Path;

class InotifyWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
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

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        return new InotifyWatcher(
            $config,
            $this->createLogger(),
            $this->commandDetector->reveal(),
            $this->osValidator->reveal()
        );
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(true));

        self::assertTrue(yield $watcher->isSupported());
    }

    public function testNotSupportedOnNonLinux(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->osValidator->isLinux()->willReturn(false);
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(true));
        self::assertFalse(yield $watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->osValidator->isLinux()->willReturn(true);
        $this->commandDetector->commandExists('inotifywait')->willReturn(new Success(false));
        self::assertFalse(yield $watcher->isSupported());
    }

    public function testMove(): Generator
    {
        $process = yield $this->startProcess();

        yield $this->delay();

        $this->workspace()->put('foobar/baz.php', 'content');
        $this->workspace()->put('foobar/bar.php', 'content');
        $this->workspace()->put('foobar/zog/bar.php', 'content');
        $this->workspace()->put('foobar/1.php', 'content');
        rename($this->workspace()->path('foobar'), $this->workspace()->path('barfoo'));

        yield $this->delay();
        yield $this->delay();

        $files = [];
        \Amp\asyncCall(function () use (&$files, $process) {
            while (null !== $file = yield $process->wait()) {
                $path = Path::makeRelative($file->path(), $this->workspace()->path());
                $files[$path] = true;
            }
        });

        yield new Delayed(10);
        $process->stop();

        self::assertArrayHasKey('barfoo/bar.php', $files);
        self::assertArrayHasKey('barfoo/baz.php', $files);
        self::assertArrayHasKey('barfoo/zog/bar.php', $files);

        $process->stop();
    }
}
