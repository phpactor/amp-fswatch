<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Process\ProcessInputStream;
use DateTimeImmutable;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Parser\LineParser;
use Phpactor\AmpFsWatch\Watcher;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FindWatcher implements Watcher
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var LineParser
     */
    private $lineParser;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $pollInterval;

    /**
     * @var DateTimeImmutable
     */
    private $lastUpdate;

    public function __construct(string $path, int $pollInterval, LoggerInterface $logger, ?LineParser $lineParser = null)
    {
        $this->path = $path;
        $this->lineParser = $lineParser ?: new LineParser();
        $this->logger = $logger;
        $this->pollInterval = $pollInterval;
    }

    public function monitor(callable $callback): void
    {
        $this->updateDateReference();

        \Amp\asyncCall(function () use ($callback) {
            while (true) {
                yield from $this->search($callback);
                yield new Delayed($this->pollInterval);
            }
        });
    }

    /**
     * @return Generator<Promise>
     */
    private function search(callable $callback): Generator
    {
        $start = microtime(true);
        $process = yield $this->startProcess();
        
        $stdout = $process->getStdout();
        $this->feedCallback($stdout, $callback);
        
        $exitCode = yield $process->join();
        $stop = microtime(true);
        $this->updateDateReference();
        $this->logger->debug(sprintf(
            'Find process "%s" done in %s seconds',
            $process->getCommand(),
            number_format($stop - $start, 2)
        ));
        
        if ($exitCode === 0) {
            return;
        }
        
        throw new RuntimeException(sprintf(
            'Process "%s" exited with error code %s',
            $process->getCommand(),
            $exitCode
        ));
    }

    private function feedCallback(ProcessInputStream $stream, callable $callback)
    {
        $this->lineParser->stream($stream, function (string $line) use ($callback) {
            $callback(new ModifiedFile($line, is_file($line) ? ModifiedFile::TYPE_FILE : ModifiedFile::TYPE_FOLDER));
        });
    }

    private function startProcess()
    {
        return \Amp\call(function () {
            $process = new Process([
                'find',
                $this->path,
                '-newermt',
                $this->lastUpdate->format('Y-m-d H:i:s.u'),
                '-mindepth',
                1
            ]);

            $pid = yield $process->start();

            if (!$process->isRunning()) {
                throw new RuntimeException(sprintf(
                    'Could not start process: %s',
                    $process->getCommand()
                ));
            }

            return $process;
        });
    }

    private function updateDateReference()
    {
        $this->lastUpdate = new DateTimeImmutable();
    }
}
