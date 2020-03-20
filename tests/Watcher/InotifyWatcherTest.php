<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Delayed;
use Amp\Loop;
use Closure;
use Generator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher\InotifyWatcher;
use Psr\Log\NullLogger;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;

class InotifyWatcherTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $this->workspace()->reset();
    }

    /**
     * @dataProvider provideMonitors
     */
    public function testMonitors(Closure $plan, array $expectedModifications): void
    {
        $watcher = new InotifyWatcher(
            $this->workspace()->path()
        );
        $watcher->start();
        $modifications = [];

        $watcher->monitor(function (ModifiedFile $modification) use (&$modifications) {
            $modifications[] = $modification;
        });

        Loop::run(function () use ($plan) {
            $generator = $plan();
            yield from $generator;
            Loop::stop();
        });

        Assert::assertCount(4, $modifications);
    }

    /**
     * @return Generator<Promise>
     */
    public function provideMonitors(): Generator
    {
        yield [
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
                new ModifiedFile($this->workspace()->path('foobar'))
            ]
        ];
    }
}
