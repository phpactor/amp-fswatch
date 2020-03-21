<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Amp\Delayed;
use Closure;
use Phpactor\AmpFsWatch\ModifiedFile;
use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class FindWatcherTest extends WatcherTestCase
{
    protected function createWatcher(): Watcher
    {
        return new FindWatcher($this->workspace()->path(), 100, $this->createLogger());
    }

    public function testDoesNotPickFilesExistingWhenStarted(): void
    {
        $this->workspace()->put('foobar', '');

        $watcher = $this->createWatcher();
        $modifications = $this->runLoop($watcher, function () {
            yield new Delayed(50);
        });

        $this->assertEquals([], $modifications);
    }

    public function testPicksModifiedFile(): void
    {
        $this->workspace()->put('foobar', '');

        $watcher = $this->createWatcher();
        $modifications = $this->runLoop($watcher, function () {
            yield new Delayed(100);
            $this->workspace()->put('foobar', '');
            yield new Delayed(100);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }

    public function testPicksNewFile(): void
    {
        $watcher = $this->createWatcher();
        $modifications = $this->runLoop($watcher, function () {
            yield new Delayed(100);
            $this->workspace()->put('barfoo', '');
            yield new Delayed(100);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }

    public function testPicksNewFolder(): void
    {
        $watcher = $this->createWatcher();
        $modifications = $this->runLoop($watcher, function () {
            yield new Delayed(100);
            mkdir($this->workspace()->path('barfoo'));
            yield new Delayed(100);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FOLDER),
        ], $modifications);
    }
}
