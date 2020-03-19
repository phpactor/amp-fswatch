<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\Watcher\InotifyWatcher;
use Psr\Log\NullLogger;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;

class InotifyWatcherTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $this->workspace()->reset();
    }

    public function testWatch(): void
    {
        $watcher = new InotifyWatcher(
            $this->workspace()->path(),
            new NullLogger()
        );
    }
}
