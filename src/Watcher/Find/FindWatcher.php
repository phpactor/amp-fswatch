<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Process\ProcessInputStream;
use DateTimeImmutable;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Parser\LineParser;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FindWatcher implements Watcher, WatcherProcess
{
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

    /**
     * @var bool
     */
    private $running = true;

    public function __construct(
        int $pollInterval,
        LoggerInterface $logger,
        ?LineParser $lineParser = null
    ) {
        $this->lineParser = $lineParser ?: new LineParser();
        $this->logger = $logger;
        $this->pollInterval = $pollInterval;
    }

    public function watch(array $paths, callable $callback): WatcherProcess
    {
        $this->logger->info(sprintf('Polling at interval of "%s" milliseconds for changes paths "%s"', $this->pollInterval, implode('", "', $paths)));

        $this->updateDateReference();

        \Amp\asyncCall(function () use ($paths, $callback) {
            while ($this->running) {
                $searches = [];
                foreach ($paths as $path) {
                    $searches[] = $this->search($path, $callback);
                }
                yield \Amp\Promise\all($searches);
                $this->updateDateReference();
                yield new Delayed($this->pollInterval);
            }
        });

        return $this;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isSupported(): bool
    {
        return true;
    }

    private function search(string $path, callable $callback): Promise
    {
        return \Amp\call(function () use ($path, $callback) {
            $start = microtime(true);
            $process = yield $this->startProcess($path);

            $stdout = $process->getStdout();

            $this->feedCallback($stdout, $callback);

            $exitCode = yield $process->join();
            $stop = microtime(true);
            $this->logger->debug(sprintf(
                'Find process "%s" done in %s seconds',
                $process->getCommand(),
                number_format($stop - $start, 2)
            ));

            if ($exitCode === 0) {
                return;
            }

            $stderr = \Amp\Promise\wait($process->getStderr()->read());
            $this->logger->error(sprintf(
                'Process "%s" exited with error code %s: %s',
                $process->getCommand(),
                $exitCode,
                $stderr
            ));
        });
    }

    private function feedCallback(ProcessInputStream $stream, callable $callback): void
    {
        $this->lineParser->stream($stream, function (string $line) use ($callback) {
            $callback(new ModifiedFile($line, is_file($line) ? ModifiedFile::TYPE_FILE : ModifiedFile::TYPE_FOLDER));
        });
    }

    private function startProcess(string $path): Promise
    {
        return \Amp\call(function () use ($path) {
            $process = new Process([
                'find',
                $path,
                '-mindepth',
                '1',
                '-newermt',
                $this->lastUpdate->format('Y-m-d H:i:s.u'),
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

    private function updateDateReference(): void
    {
        $this->lastUpdate = new DateTimeImmutable();
    }
}
