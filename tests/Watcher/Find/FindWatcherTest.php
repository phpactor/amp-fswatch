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
        return new FindWatcher(100, $this->createLogger());
    }

    public function testDoesNotPickFilesExistingWhenStarted(): void
    {
        $this->workspace()->put('foobar', '');

        $modifications = $this->runLoop([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(50);
        });

        $this->assertEquals([], $modifications);
    }

    public function testPicksModifiedFile(): void
    {
        $this->workspace()->put('foobar', '');

        $modifications = $this->runLoop([
            $this->workspace()->path(),
        ], function () {
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
        $modifications = $this->runLoop([
            $this->workspace()->path(),
        ], function () {
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
        $modifications = $this->runLoop([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(100);
            mkdir($this->workspace()->path('barfoo'));
            yield new Delayed(100);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FOLDER),
        ], $modifications);
    }

    public function testMultiplePaths(): void
    {
        $this->workspace()->mkdir('foobar');
        $this->workspace()->mkdir('barfoo');

        $modifications = $this->runLoop([
            $this->workspace()->path('barfoo'),
            $this->workspace()->path('foobar'),
        ], function () {
            yield new Delayed(200);
            $this->workspace()->put('barfoo/foobar', '');
            $this->workspace()->put('foobar/barfoo', '');
            yield new Delayed(200);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo/foobar'), ModifiedFile::TYPE_FILE),
            new ModifiedFile($this->workspace()->path('foobar/barfoo'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }
}
