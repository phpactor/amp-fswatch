<?php

namespace Phpactor\AmpFsWatch\Watcher\Inotify;

use Amp\Process\Process;
use Amp\Promise;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Parser\LineParser;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use RuntimeException;

class InotifyWatcher implements Watcher, WatcherProcess
{
    const INOTIFY_CMD = 'inotifywait';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LineParser
     */
    private $parser;

    /**
     * @var Process
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

    public function __construct(
        LoggerInterface $logger,
        ?CommandDetector $commandDetector = null,
        ?OsDetector $osDetector = null,
        ?LineParser $parser = null
    ) {
        $this->logger = $logger;
        $this->parser = $parser ?: new LineParser();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->osDetector = $osDetector ?: new OsDetector(PHP_OS);
    }

    /**
     * {@inheritDoc}
     */
    public function watch(array $paths, callable $callback): WatcherProcess
    {
        \Amp\asyncCall(function () use ($paths, $callback) {
            $this->process = yield $this->startProcess($paths);
            $this->feedCallback($this->process, $callback);

            $stderr = '';
            $stderrStream = $this->process->getStderr();
            $exitCode = yield $this->process->join();

            if ($exitCode === 0) {
                return;
            }

            $stderr = \Amp\Promise\wait($this->process->getStderr()->read());

            throw new RuntimeException(sprintf(
                'Process "%s" exited with error code %s: %s',
                $this->process->getCommand(),
                $exitCode,
                $stderr
            ));
        });

        return $this;
    }

    public function stop(): void
    {
        if (null === $this->process) {
            throw new RuntimeException(
                'Inotifywait process was not started, cannot call stop()'
            );
        }
        $this->process->signal(SIGTERM);
    }

    /**
     * @param array<string> $paths
     */
    private function startProcess(array $paths): Promise
    {
        return \Amp\call(function () use ($paths) {
            $process = new Process(array_merge([
                self::INOTIFY_CMD,
                '-r',
                '-emodify,create,delete',
                '--monitor',
                '--csv',
            ], $paths));

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

    private function feedCallback(Process $process, callable $callback): void
    {
        $this->parser->stream($process->getStdout(), function (string $line) use ($callback) {
            $this->logger->debug(sprintf('EVENT: %s', $line));
            $event = InotifyEvent::createFromCsv($line);

            $builder = ModifiedFileBuilder::fromPathSegments(
                $event->watchedFileName(),
                $event->eventFilename()
            );

            if ($event->hasEventName('ISDIR')) {
                $builder = $builder->asFolder();
            }

            $callback($builder->build());
        });
    }

    public function isSupported(): bool
    {
        if (!$this->osDetector->isLinux()) {
            return false;
        }

        return $this->commandDetector->commandExists(self::INOTIFY_CMD);
    }
}
