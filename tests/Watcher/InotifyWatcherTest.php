<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Phpactor\AmpFsWatch\Watcher\InotifyWatcher;

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
