<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\PhpPollWatcher;

use Generator;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\PhpPollWatcher\PhpPollWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class PhpPollWatcherTest extends WatcherTestCase
{
    protected function createWatcher(WatcherConfig $config): Watcher
    {
        return new PhpPollWatcher(
            $config,
            $this->createLogger()
        );
    }

    public function testIsSupported(): Generator
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue(yield $watcher->isSupported());
    }

    public function testRemoval(): Generator
    {
        $this->markTestSkipped('Not supported');
    }
}
