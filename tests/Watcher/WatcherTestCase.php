<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Delayed;
use Amp\Promise;
use Closure;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;
use Throwable;

abstract class WatcherTestCase extends IntegrationTestCase

{
    abstract protected function createWatcher(): Watcher;

    public function testSingleFileChange(): Generator
    {
        $process = $this->startProcess();
        yield $this->delay();
        $this->workspace()->put('foobar', '');
        yield $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            yield $process->wait()
        );

        $process->stop();
    }

    public function testMultipleSameFile(): Generator
    {
        $process = $this->startProcess();

        yield $this->delay();
        $this->workspace()->put('foobar', '');
        $this->workspace()->put('foobar', 'foobar');
        yield $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            yield $process->wait()
        );

        $process->stop();
    }

    public function testDirectory(): Generator
    {
        $process = $this->startProcess();

        yield $this->delay();
        $this->workspace()->mkdir('foobar');
        yield $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FOLDER
            ),
            yield $process->wait()
        );

        $process->stop();
    }

    public function testRemoval(): Generator
    {
        $this->workspace()->put('foobar', '');

        $process = $this->startProcess();

        yield $this->delay();

        unlink($this->workspace()->path('foobar'));

        yield $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            yield $process->wait()
        );

        $process->stop();
    }

    public function testMultiplePaths(): Generator
    {
        $this->workspace()->mkdir('foobar');
        $this->workspace()->mkdir('barfoo');

        $process = $this->startProcess([
            $this->workspace()->path('barfoo'),
            $this->workspace()->path('foobar'),
        ]);

        yield $this->delay();

        $this->workspace()->put('barfoo/foobar', '');
        $this->workspace()->put('foobar/barfoo', '');

        yield $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('barfoo/foobar'),
                ModifiedFile::TYPE_FILE
            ),
            yield $process->wait()
        );
        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar/barfoo'),
                ModifiedFile::TYPE_FILE
            ),
            yield $process->wait()
        );

        $process->stop();
    }

    protected function delay(): Delayed
    {
        return new Delayed(10);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace()->reset();
    }

    protected function createLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, $message, array $context = [])
            {
                //fwrite(STDERR, sprintf('[%s] [%s] %s', microtime(), $level, $message)."\n");
            }
        };
    }

    private function startProcess(?array $paths = []): WatcherProcess
    {
        $paths = $paths ?: [ $this->workspace()->path() ];;
        $watcher = $this->createWatcher();
        return $watcher->watch($paths);
    }

    abstract public function testIsSupported(): void;
}
