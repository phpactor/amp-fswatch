<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\PatternMatching;

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\PatternMatching\PatternMatchingWatcher;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;

class PatternMatchingWatcherTest extends AsyncTestCase
{
    protected function createWatcher(array $patterns, array $modifiedFiles): Watcher
    {
        return new PatternMatchingWatcher(new TestWatcher(new ModifiedFileQueue($modifiedFiles)), $patterns);
    }

    public function testFiltersFiles()
    {
        $process = yield $this->createWatcher(['*.php'], [
            $this->createFile('Foobar.php'),
            $this->createFile('Foobar.php~'),
            $this->createFile('timestamp'),
        ])->watch();

        $files = [];
        while (null !== $file = yield $process->wait()) {
            $files[] = $file;
        }

        self::assertCount(1, $files);
        self::assertEquals($this->createFile('Foobar.php'), $files[0]);
    }

    public function testIsSupported(): Generator
    {
        self::assertTrue(yield $this->createWatcher([], [])->isSupported());
    }

    private function createFile(string $name): ModifiedFile
    {
        return new ModifiedFile($name, ModifiedFile::TYPE_FILE);
    }
}
