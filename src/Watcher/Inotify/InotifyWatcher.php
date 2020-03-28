<?php

namespace Phpactor\AmpFsWatch\Watcher\Inotify;

use Amp\ByteStream\LineReader;
use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class InotifyWatcher implements Watcher, WatcherProcess
{
    const INOTIFY_CMD = 'inotifywait';
    const POLL_TIME = 1;


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
     * @var OsDetector
     */
    private $osDetector;

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
        ?CommandDetector $commandDetector = null,
        ?OsDetector $osDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->osDetector = $osDetector ?: new OsDetector(PHP_OS);
        $this->stack = new ModifiedFileStack();
        $this->config = $config;
    }

    public function watch(): Promise
    {
        return \Amp\call(function () {
            $this->running = true;
            $this->process = yield $this->startProcess();
            $this->feedStack($this->process);

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
                self::INOTIFY_CMD,
                '-r',
                '-emodify,create,delete',
                '--monitor',
                '--csv',
            ], $this->config->paths()));

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
        \Amp\asyncCall(function () use ($process) {
            $lineReader = new LineReader($process->getStdout());
            while (null !== $line = yield $lineReader->readLine()) {
                $event = InotifyEvent::createFromCsv($line);

                $builder = ModifiedFileBuilder::fromPathSegments(
                    $event->watchedFileName(),
                    $event->eventFilename()
                );

                if ($event->hasEventName('ISDIR')) {
                    $builder = $builder->asFolder();
                }

                $this->stack->append($builder->build());
            }
        });
    }

    public function isSupported(): Promise
    {
        if (!$this->osDetector->isLinux()) {
            return new Success(false);
        }

        return $this->commandDetector->commandExists(self::INOTIFY_CMD);
    }
}
