<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Amp\Delayed;
use Phpactor\AmpFsWatch\ModifiedFile;
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
        return new FindWatcher(50, $this->createLogger(), $this->commandDetector->reveal());
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
