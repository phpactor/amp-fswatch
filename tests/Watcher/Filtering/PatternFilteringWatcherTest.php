<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Filtering;

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Filtering\PatternFilteringWatcher;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;

class PatternFilteringWatcherTest extends AsyncTestCase
{
    protected function createWatcher(string $pattern, array $modifiedFiles): Watcher
    {
        return new PatternFilteringWatcher(new TestWatcher(new ModifiedFileQueue($modifiedFiles)), $pattern);
    }

    public function testFiltersFiles()
    {
        $process = yield $this->createWatcher('*.php', [
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
        self::assertTrue(yield $this->createWatcher('*.php', [])->isSupported());
    }

    private function createFile(string $name): ModifiedFile
    {
        return new ModifiedFile($name, ModifiedFile::TYPE_FILE);
    }
}
