<?php

namespace Phpactor\AmpFsWatch\Watcher\FsWatch;

use Amp\Delayed;
use Amp\Process\Process;
use Amp\Promise;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Parser\LineParser;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
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
     * @var LineParser
     */
    private $parser;

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
     * @var array<string>
     */
    private $paths;

    public function __construct(
        LoggerInterface $logger,
        ?CommandDetector $commandDetector = null,
        ?LineParser $parser = null
    ) {
        $this->logger = $logger;
        $this->parser = $parser ?: new LineParser();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->stack = new ModifiedFileStack();
    }

    /**
     * {@inheritDoc}
     */
    public function watch(array $paths): WatcherProcess
    {
        $this->paths = $paths;

        \Amp\asyncCall(function () {
            $this->process = yield $this->startProcess($this->paths);
            $this->running = true;
            $this->feedStack($this->process);
        });

        return $this;
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
     * @param array<string> $paths
     */
    private function startProcess(array $paths): Promise
    {
        return \Amp\call(function () use ($paths) {
            $process = new Process(array_merge([
                self::CMD,
            ], $paths, [
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
        $this->parser->stream($process->getStdout(), function (string $line) {
            $builder = ModifiedFileBuilder::fromPath($line);
            if (file_exists($line) && !is_file($line)) {
                $builder->asFolder();
            }
            $this->stack->append($builder->build());
        });
    }

    public function isSupported(): bool
    {
        return $this->commandDetector->commandExists(self::CMD);
    }
}
