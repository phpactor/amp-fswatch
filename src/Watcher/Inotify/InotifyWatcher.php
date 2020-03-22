<?php

namespace Phpactor\AmpFsWatch\Watcher\Inotify;

use Amp\Process\Process;
use Amp\Promise;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Parser\LineParser;
use Phpactor\AmpFsWatch\Watcher;
use Psr\Log\LoggerInterface;
use RuntimeException;

class InotifyWatcher implements Watcher
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LineParser
     */
    private $parser;

    public function __construct(LoggerInterface $logger, ?LineParser $parser = null)
    {
        $this->logger = $logger;
        $this->parser = $parser ?: new LineParser();
    }

    /**
     * {@inheritDoc}
     */
    public function monitor(array $paths, callable $callback): void
    {
        \Amp\asyncCall(function () use ($paths, $callback) {
            $process = yield $this->startProcess($paths);
            $this->feedCallback($process, $callback);

            $stderr = '';
            $stderrStream = $process->getStderr();
            \Amp\asyncCall(function () use (&$stderr, $stderrStream) {
                $stderr .= yield $stderrStream->read();
            });
            $exitCode = yield $process->join();

            if ($exitCode === 0) {
                return;
            }

            throw new RuntimeException(sprintf(
                'Process "%s" exited with error code %s: %s',
                $process->getCommand(),
                $exitCode,
                $stderr
            ));
        });
    }

    /**
     * @param array<string> $paths
     */
    private function startProcess(array $paths): Promise
    {
        return \Amp\call(function () use ($paths) {
            $process = new Process(array_merge([
                'inotifywait',
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
}
