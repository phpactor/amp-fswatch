<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Delayed;
use Amp\Promise;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;

abstract class WatcherTestCase extends IntegrationTestCase
{
    const DELAY_MILLI = 20;

    abstract protected function createWatcher(WatcherConfig $config): Watcher;

    public function testSingleFileChange(): Generator
    {
        $process = yield $this->startProcess();
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

    public function testSingleFileChangeWithModificationTimeInPast(): Generator
    {
        $process = yield $this->startProcess();
        yield $this->delay();
        touch($this->workspace()->path('foobar'), time() - 3600);
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
        $process = yield $this->startProcess();

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
        $process = yield $this->startProcess();

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

        $process = yield $this->startProcess();

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

        $process = yield $this->startProcess([
            $this->workspace()->path('barfoo'),
            $this->workspace()->path('foobar'),
        ]);

        yield $this->delay();

        $this->workspace()->put('barfoo/foobar', '');
        $this->workspace()->put('foobar/barfoo', '');

        yield $this->delay();

        $files = [];
        for ($i = 0; $i < 2; $i++) {
            $file = yield $process->wait();
            $files[$file->path()] = $file;
        }

        self::assertArrayHasKey($this->workspace()->path('barfoo/foobar'), $files);
        self::assertArrayHasKey($this->workspace()->path('foobar/barfoo'), $files);

        $process->stop();
    }

    public function testReturnsNameAsString()
    {
        self::assertIsString($this->createWatcher(new WatcherConfig([]))->describe());
    }

    protected function delay(): Delayed
    {
        return new Delayed(self::DELAY_MILLI);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTimeout(5000);
        $this->workspace()->reset();
    }

    protected function createLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, $message, array $context = [])
            {
                if ($level === 'debug') {
                    return;
                }
                fwrite(STDERR, sprintf('[%s] [%s] %s', microtime(), $level, $message)."\n");
            }
        };
    }

    /**
     * @param array<string> $paths
     *
     * @return Promise<WatcherProcess>
     */
    protected function startProcess(?array $paths = []): Promise
    {
        $paths = $paths ?: [ $this->workspace()->path() ];
        $watcher = $this->createWatcher(new WatcherConfig($paths));
        return $watcher->watch();
    }

    abstract public function testIsSupported(): Generator;
}
