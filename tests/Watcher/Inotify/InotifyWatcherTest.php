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
            $this->workspace()->path(),
            $this->createLogger()
        );
    }

    /**
     * @dataProvider provideMonitors
     */
    public function testMonitors(Closure $plan, array $expectedModifications): void
    {
        $watcher = $this->createWatcher();
        $modifications = $this->runLoop($watcher, $plan);

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
}
