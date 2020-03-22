<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Amp\Delayed;
use Phpactor\AmpFsWatch\ModifiedFile;
use Generator;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class FindWatcherTest extends WatcherTestCase
{
    const PLAN_DELAY = 100;

    protected function createWatcher(): Watcher
    {
        return new FindWatcher(50, $this->createLogger());
    }

    public function testDoesNotPickFilesExistingWhenStarted(): Generator
    {
        $this->workspace()->put('foobar', '');

        $modifications = yield $this->monitor([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(self::PLAN_DELAY);
        });

        $this->assertEquals([], $modifications);
    }

    public function testPicksModifiedFile(): Generator
    {
        $this->workspace()->put('foobar', '');

        $modifications = yield $this->monitor([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(self::PLAN_DELAY);
            $this->workspace()->put('foobar', '');
            yield new Delayed(self::PLAN_DELAY);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }

    public function testPicksNewFile(): Generator
    {
        $modifications = yield $this->monitor([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(self::PLAN_DELAY);
            $this->workspace()->put('barfoo', '');
            yield new Delayed(self::PLAN_DELAY);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }

    public function testPicksNewFolder(): Generator
    {
        $modifications = yield $this->monitor([
            $this->workspace()->path(),
        ], function () {
            yield new Delayed(self::PLAN_DELAY);
            mkdir($this->workspace()->path('barfoo'));
            yield new Delayed(self::PLAN_DELAY);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FOLDER),
        ], $modifications);
    }

    public function testMultiplePaths(): Generator
    {
        $this->workspace()->mkdir('foobar');
        $this->workspace()->mkdir('barfoo');

        $modifications = yield $this->monitor([
            $this->workspace()->path('barfoo'),
            $this->workspace()->path('foobar'),
        ], function () {
            yield new Delayed(self::PLAN_DELAY);
            $this->workspace()->put('barfoo/foobar', '');
            $this->workspace()->put('foobar/barfoo', '');
            yield new Delayed(self::PLAN_DELAY);
        });

        $this->assertEquals([
            new ModifiedFile($this->workspace()->path('barfoo/foobar'), ModifiedFile::TYPE_FILE),
            new ModifiedFile($this->workspace()->path('foobar/barfoo'), ModifiedFile::TYPE_FILE),
        ], $modifications);
    }
}
