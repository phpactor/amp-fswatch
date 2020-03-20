<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Delayed;
use Amp\Loop;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\FileModification;
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
        $watcher->start();
        $modifications = [];

        $watcher->monitor(function (FileModification $modification) use (&$modifications) {
            $modifications[] = $modification;
        });

        Loop::run(function () {
            yield new Delayed(10);
            $this->workspace()->put('foobar', '');
            $this->workspace()->put('foobar', '');
            yield new Delayed(10);
            $this->workspace()->put('foobar', '');
            $this->workspace()->put('foobar', '');
            yield new Delayed(10);
            Loop::stop();
        });

        Assert::assertCount(4, $modifications);
    }
}
