<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Amp\Delayed;
use Closure;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class InotifyWatcherTest extends WatcherTestCase
{
    protected function createWatcher(): Watcher
    {
        return new InotifyWatcher(
            $this->createLogger()
        );
    }

    /**
     * @dataProvider provideMonitors
     */
    public function testMonitors(Closure $plan, array $expectedModifications): Generator
    {
        $modifications = yield $this->monitor([
            $this->workspace()->path()
        ], $plan);

        $this->assertEquals($expectedModifications, $modifications);
    }

    public function provideMonitors(): Generator
    {
        yield 'multiple single files' => [
            function () {
                yield new Delayed(10);
                $this->workspace()->put('foobar', '');
                $this->workspace()->put('foobar', '');
                yield new Delayed(10);
                $this->workspace()->put('foobar', '');
                $this->workspace()->put('foobar', '');
                yield new Delayed(10);
            },
            [
                new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
                new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
                new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
                new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
            ]
        ];

        yield 'directories' => [
            function () {
                yield new Delayed(10);
                mkdir($this->workspace()->path('barfoo'));
                yield new Delayed(10);
            },
            [
                new ModifiedFile($this->workspace()->path('barfoo'), ModifiedFile::TYPE_FOLDER),
            ]
        ];

        yield 'file removal' => [
            function () {
                $this->workspace()->put('foobar', 'asd');
                yield new Delayed(10);
                unlink($this->workspace()->path('foobar'));
                yield new Delayed(10);
            },
            [
                new ModifiedFile($this->workspace()->path('foobar'), ModifiedFile::TYPE_FILE),
            ]
        ];
    }

    public function testMultiplePaths(): Generator
    {
        $this->workspace()->mkdir('foobar');
        $this->workspace()->mkdir('barfoo');

        $modifications = yield $this->monitor([
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
