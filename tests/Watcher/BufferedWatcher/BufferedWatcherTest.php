<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\BufferedWatcher;

use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher\BufferedWatcher\BufferedWatcher;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;
use RuntimeException;

class BufferedWatcherTest extends AsyncTestCase
{
    public function testBufferedWatcher(): Generator
    {
        $expectedFile1 = new ModifiedFile('/foo', ModifiedFile::TYPE_FILE);
        $expectedFile2 = new ModifiedFile('/bar', ModifiedFile::TYPE_FILE);
        $queue = new ModifiedFileQueue([
            $expectedFile2,
            $expectedFile1,
        ]);
        $bufferedWatcher = new BufferedWatcher(new TestWatcher($queue), 100);
        $process = yield $bufferedWatcher->watch();
        $file1 = yield $process->wait();
        $file2 = yield $process->wait();

        self::assertSame($expectedFile1, $file1);
        self::assertSame($expectedFile2, $file2);
    }

    public function testDeduplicatesFiles(): Generator
    {
        $expectedFile1 = new ModifiedFile('/foo', ModifiedFile::TYPE_FILE);
        $expectedFile2 = new ModifiedFile('/foo', ModifiedFile::TYPE_FILE);
        $expectedFile3 = new ModifiedFile('/bar', ModifiedFile::TYPE_FILE);
        $expectedFile4 = new ModifiedFile('/bar', ModifiedFile::TYPE_FILE);
        $queue = new ModifiedFileQueue([
            $expectedFile4,
            $expectedFile3,
            $expectedFile2,
            $expectedFile1,
        ]);
        $bufferedWatcher = new BufferedWatcher(new TestWatcher($queue), 100);
        $process = yield $bufferedWatcher->watch();
        $file1 = yield $process->wait();
        $file2 = yield $process->wait();

        self::assertSame($expectedFile2, $file1);
        self::assertSame($expectedFile4, $file2);
    }

    public function testReturnsNulLWhenInnerWatcherStops(): Generator
    {
        $expectedFile1 = new ModifiedFile('/foo', ModifiedFile::TYPE_FILE);
        $queue = new ModifiedFileQueue([
            $expectedFile1,
        ]);
        $bufferedWatcher = new BufferedWatcher(new TestWatcher($queue), 100);
        $process = yield $bufferedWatcher->watch();
        $file1 = yield $process->wait();
        $file2 = yield $process->wait();

        self::assertSame($expectedFile1, $file1);
        self::assertNull($file2);
    }

    public function testErrorsBubbleUp(): Generator
    {
        $this->expectExceptionMessage('sorry');
        $expectedFile1 = new ModifiedFile('/foo', ModifiedFile::TYPE_FILE);
        $queue = new ModifiedFileQueue([
            $expectedFile1,
        ]);
        $bufferedWatcher = new BufferedWatcher(new TestWatcher($queue, 100, new RuntimeException('sorry')), 10);
        $process = yield $bufferedWatcher->watch();
        yield new Delayed(100);
        yield $process->wait();
    }
}
