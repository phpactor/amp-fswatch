<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class InotifyWatcherTest extends WatcherTestCase
{
    protected function createWatcher(): InotifyWatcher
    {
        return new InotifyWatcher(
            $this->workspace()->path(),
            $this->createLogger()
        );
    }
}
