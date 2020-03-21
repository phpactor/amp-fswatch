<?php

namespace Phpactor\AmpFsWatch\Watcher;

use Amp\Process\Process;
use Amp\Process\ProcessInputStream;
use Amp\Promise;
use Generator;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Psr\Log\LoggerInterface;
use RuntimeException;

class InotifyWatcher implements Watcher
{
    /**
     * @var Process
     */
    private $process;

    /**
     * @var string
     */
    private $buffer;

    /**
     * @var string
     */
    private $path;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(string $path, LoggerInterface $logger)
    {
        $this->path = $path;
        $this->logger = $logger;
    }

    public function start(): void
    {
        $this->process = \Amp\Promise\wait($this->startProcess());
    }

    /**
     * {@inheritDoc}
     */
    public function monitor(callable $callback): void
    {
        \Amp\asyncCall(function () use ($callback) {
            $buffer = '';
            $stdout = $this->process->getStdout();

            $this->feedCallback($stdout, $callback);

            $stderr = '';
            $stderrStream = $this->process->getStderr();
            \Amp\asyncCall(function () use (&$stderr, $stderrStream) {
                $stderr .= yield $stderrStream->read();
            });
            $exitCode = yield $this->process->join();

            if ($exitCode === 0) {
                return;
            }

            throw new RuntimeException(sprintf(
                'Process "%s" exited with error code %s: %s',
                $this->process->getCommand(),
                $exitCode,
                $stderr
            ));
        });
    }

    private function startProcess(): Promise
    {
        return \Amp\call(function () {
            $process = new Process([
                'inotifywait',
                $this->path,
                '-r',
                '-emodify,create,delete',
                '--monitor',
                '--csv',
            ]);

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

    private function feedCallback(ProcessInputStream $stdout, callable $callback): void
    {
        \Amp\asyncCall(function () use ($stdout, $callback) {
            while (null !== $chunk = yield $stdout->read()) {
                foreach (str_split($chunk) as $char) {
                    $this->buffer .= $char;

                    if ($char !== "\n") {
                        continue;
                    }

                    $line = $this->buffer;
                    $this->logger->debug(sprintf('EVENT: %s', $line));
                    $this->buffer = '';
                    $event = InotifyEvent::createFromCsv($line);

                    $builder = ModifiedFileBuilder::fromPathSegments(
                        $event->watchedFileName(),
                        $event->eventFilename()
                    );

                    if ($event->hasEventName('ISDIR')) {
                        $builder = $builder->asFolder();
                    }

                    $callback($builder->build());
                }
            }
        });
    }
}
