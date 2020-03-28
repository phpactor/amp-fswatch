<?php

namespace Phpactor\AmpFsWatch\Watcher\FsWatch;

use Amp\ByteStream\LineReader;
use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class FsWatchWatcher implements Watcher, WatcherProcess
{
    private const CMD = 'fswatch';
    private const POLL_TIME = 1;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Process|null
     */
    private $process;

    /**
     * @var CommandDetector
     */
    private $commandDetector;

    /**
     * @var ModifiedFileStack
     */
    private $stack;

    /**
     * @var bool
     */
    private $running;

    /**
     * @var WatcherConfig
     */
    private $config;

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->stack = new ModifiedFileStack();
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function watch(): Promise
    {
        return \Amp\call(function () {
            $this->process = yield $this->startProcess();
            $this->running = true;
            $this->feedStack($this->process);
            return $this;
        });
    }

    public function wait(): Promise
    {
        return \Amp\call(function () {
            while (false === $this->process->isRunning()) {
                yield new Delayed(self::POLL_TIME);
            }

            while ($this->running) {
                $this->stack = $this->stack->compress();

                if ($next = $this->stack->unshift()) {
                    return $next;
                }

                yield new Delayed(self::POLL_TIME);
            }

            return null;
        });
    }

    public function stop(): void
    {
        if (null === $this->process) {
            throw new RuntimeException(
                'Inotifywait process was not started, cannot call stop()'
            );
        }
        $this->running = false;
        $this->process->signal(SIGTERM);
    }

    /**
     * @return Promise<Process>
     */
    private function startProcess(): Promise
    {
        return \Amp\call(function () {
            $process = new Process(array_merge([
                self::CMD,
            ], $this->config->paths(), [
                '-r',
                '--event=Created',
                '--event=Updated',
                '--event=Removed'
            ]));

            $pid = yield $process->start();
            $this->logger->debug(sprintf('Started "%s"', $process->getCommand()));

            if (!$process->isRunning()) {
                throw new RuntimeException(sprintf(
                    'Could not start process: %s',
                    $process->getCommand()
                ));
            }

            return $process;
        });
    }

    private function feedStack(Process $process): void
    {
        $reader = new LineReader($process->getStdout());
        \Amp\asyncCall(function () use ($reader) {
            while (null !== $line = yield $reader->readLine()) {
                $builder = ModifiedFileBuilder::fromPath($line);
                if (file_exists($line) && !is_file($line)) {
                    $builder->asFolder();
                }
                $this->stack->append($builder->build());
            }
        });
    }

    public function isSupported(): Promise
    {
        return $this->commandDetector->commandExists(self::CMD);
    }
}
