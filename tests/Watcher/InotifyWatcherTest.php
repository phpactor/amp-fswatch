<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Delayed;
use Amp\Loop;
use Closure;
use Generator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher\InotifyWatcher;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
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
            $this->workspace()->path(),
            $this->createLogger()
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

    private function createLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, $message, array $context = array()) {
                fwrite(STDERR, sprintf('[%s] %s', $level, $message)."\n");
            }
        };
    }
}
