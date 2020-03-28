<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use Amp\ByteStream\LineReader;
use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Process\ProcessInputStream;
use DateTimeImmutable;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class FindWatcher implements Watcher, WatcherProcess
{
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

    /**
     * @var CommandDetector
     */
    private $commandDetector;

    /**
     * @var ModifiedFileStack
     */
    private $stack;

    /**
     * @var WatcherConfig
     */
    private $config;

    public function __construct(
        WatcherConfig $config,
        ?int $pollInterval = 1000,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->pollInterval = $pollInterval;
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->stack = new ModifiedFileStack();
        $this->config = $config;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $this->logger->info(sprintf(
                'Polling at interval of "%s" milliseconds for changes paths "%s"',
                $this->pollInterval,
                implode('", "', $this->config->paths())
            ));

            $this->updateDateReference();
            $this->running = true;

            \Amp\asyncCall(function () {
                while ($this->running) {
                    $searches = [];
                    foreach ($this->config->paths() as $path) {
                        $searches[] = $this->search($path);
                    }
                    yield \Amp\Promise\all($searches);
                    $this->updateDateReference();
                    yield new Delayed($this->pollInterval);
                }
            });

            return $this;
        });
    }

    public function wait(): Promise
    {
        return \Amp\call(function () {
            while ($this->running) {
                $this->stack = $this->stack->compress();

                if ($next = $this->stack->unshift()) {
                    return $next;
                }

                yield new Delayed(1);
            }
        });
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isSupported(): Promise
    {
        return $this->commandDetector->commandExists('find');
    }

    /**
     * @return Promise<void>
     */
    private function search(string $path): Promise
    {
        return \Amp\call(function () use ($path) {
            $start = microtime(true);
            $process = yield $this->startProcess($path);

            $this->feedStack($process->getStdout());

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

            $stderr = yield $process->getStderr()->read();
            $this->logger->error(sprintf(
                'Process "%s" exited with error code %s: %s',
                $process->getCommand(),
                $exitCode,
                $stderr
            ));
        });
    }

    private function feedStack(ProcessInputStream $stream): void
    {
        \Amp\asyncCall(function () use ($stream) {
            $reader = new LineReader($stream);
            while (null !== $line = yield $reader->readLine()) {
                $this->stack->append(new ModifiedFile($line, is_file($line) ? ModifiedFile::TYPE_FILE : ModifiedFile::TYPE_FOLDER));
            }
        });
    }

    /**
     * @return Promise<Process>
     */
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
