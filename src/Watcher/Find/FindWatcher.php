<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use Amp\ByteStream\LineReader;
use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Process\ProcessInputStream;
use DateTimeImmutable;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use function Amp\delay;

class FindWatcher implements Watcher, WatcherProcess
{
    /**
     * @var LoggerInterface
     */
    private $logger;

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
     * @var ModifiedFileQueue
     */
    private $queue;

    /**
     * @var WatcherConfig
     */
    private $config;

    /**
     * @var string
     */
    private $lastUpdateFile;

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->queue = new ModifiedFileQueue();
        $this->config = $config;
        $this->lastUpdateFile = $config->lastUpdateReferenceFile() ?: $this->createTempFile();
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $this->logger->info(sprintf(
                'Polling at interval of "%s" milliseconds for changes paths "%s"',
                $this->config->pollInterval(),
                implode('", "', $this->config->paths())
            ));

            $this->updateDateReference();
            $this->running = true;

            yield delay(10);

            \Amp\asyncCall(function () {
                while ($this->running) {
                    $searches = [];
                    foreach ($this->config->paths() as $path) {
                        $searches[] = $this->search($path);
                    }
                    yield \Amp\Promise\all($searches);
                    $this->updateDateReference();
                    yield new Delayed($this->config->pollInterval());
                }
            });

            return $this;
        });
    }

    public function wait(): Promise
    {
        return \Amp\call(function () {
            while ($this->running) {
                $this->queue = $this->queue->compress();

                if ($next = $this->queue->dequeue()) {
                    return $next;
                }

                yield new Delayed($this->config->pollInterval() / 2);
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

            $this->feedQueue($process->getStdout());

            $exitCode = yield $process->join();
            $stop = microtime(true);

            $this->logger->debug(sprintf(
                'pid:%s Find process "%s" done in %s seconds',
                getmypid(),
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

    private function feedQueue(ProcessInputStream $stream): void
    {
        \Amp\asyncCall(function () use ($stream) {
            $reader = new LineReader($stream);
            while (null !== $line = yield $reader->readLine()) {
                $this->logger->debug('find found: ' . $line);
                $this->queue->enqueue(new ModifiedFile($line, is_file($line) ? ModifiedFile::TYPE_FILE : ModifiedFile::TYPE_FOLDER));
            }
        });
    }

    /**
     * @return Promise<Process>
     */
    private function startProcess(string $path): Promise
    {
        return \Amp\call(function () use ($path) {
            // use ctime (inode status change time) rather than modification
            // time as vendor libraries (for example) preserve the modification
            // times.
            $process = new Process([
                'find',
                $path,
                '-mindepth',
                '1',
                '-newercc',
                $this->lastUpdateFile
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
        touch($this->lastUpdateFile);
    }

    private function formatDate(): string
    {
        return 'Y-m-d H:i:s';
    }

    private function createTempFile(): string
    {
        $name = tempnam(sys_get_temp_dir(), 'amp-fs-watch');

        if (!$name) {
            throw new RuntimeException(sprintf(
                'Could not create temporary file "%s"',
                $name
            ));
        }

        return $name;
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'find (BSD/GNU)';
    }
}
