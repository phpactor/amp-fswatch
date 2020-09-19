<?php

namespace Phpactor\AmpFsWatch\Watcher\Watchman;

use Amp\ByteStream\LineReader;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\Exception\WatcherDied;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use function Amp\ByteStream\buffer;
use Webmozart\PathUtil\Path;
use function Amp\call;

class WatchmanWatcher implements Watcher, WatcherProcess
{
    const WATCHMAN_CMD = 'watchman';

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
     * @var ModifiedFileQueue
     */
    private $queue;

    /**
     * @var bool
     */
    private $running;

    /**
     * @var WatcherConfig
     */
    private $config;

    /**
     * @var LineReader
     */
    private $lineReader;

    /**
     * @var array<ModifiedFile>
     */
    private $directoryBuffer = [];

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null,
        ?OsDetector $osDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->osDetector = $osDetector ?: new OsDetector(PHP_OS);
        $this->queue = new ModifiedFileQueue();
        $this->config = $config;
    }

    public function watch(): Promise
    {
        return call(function () {
            yield $this->watchPaths();
            $this->process = yield $this->subscribe();
            $this->lineReader = new LineReader($this->process->getStdout());

            return $this;
        });
    }

    public function wait(): Promise
    {
        return call(function () {
            while (null !== $file = array_shift($this->directoryBuffer)) {
                return $file;
            }

            while (null !== $line = yield $this->readJson()) {
                $notification = json_decode($line, true);

                if (false === $notification) {
                    throw new RuntimeException(sprintf(
                        'Could not decode JSON from watchman: %s %s',
                        $line,
                        json_last_error_msg()
                    ));
                }

                $files = array_map(function (array $file) use ($notification) {
                    $modifiedFile = ModifiedFileBuilder::fromPathSegments(
                        $notification['root'],
                        $file['name']
                    );
                    if ($file['type'] === 'd') {
                        $modifiedFile = $modifiedFile->asFolder();
                    }

                    return $modifiedFile->build();
                }, $notification['files'] ?? []);

                if (empty($files)) {
                    continue;
                }

                $file = array_shift($files);
                $this->directoryBuffer = array_merge($this->directoryBuffer, $files);

                return $file;
            };
        });
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
     * @return Promise<void>
     */
    private function watchPaths(): Promise
    {
        return call(function () {
            foreach ($this->config->paths() as $path) {
                $process = new Process([
                    self::WATCHMAN_CMD,
                    'watch-project',
                    $path
                ]);


                $pid = yield $process->start();
                $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));
                $exit = yield $process->join();

                if ($exit !== 0) {
                    throw new RuntimeException(sprintf(
                        'Watchman exited with code "%s": %s ',
                        $exit,
                        buffer($process->getStderr())
                    ));
                }
            }
        });
    }

    public function isSupported(): Promise
    {
        if (!$this->osDetector->isLinux()) {
            return new Success(false);
        }

        return $this->commandDetector->commandExists(self::WATCHMAN_CMD);
    }

    /**
     * @return Promise<Process>
     */
    private function subscribe(): Promise
    {
        return call(function () {
            $process = new Process([
                self::WATCHMAN_CMD,
                '-j',
                '-p',
                '--no-pretty'
            ]);
            $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));
            $paths = $this->config->paths();
            $path = reset($paths);

            $pid = yield $process->start();
            $payload = (string)json_encode([
                'subscribe',
                $path,
                'ampfs-watch',
                [
                    'expression' => [
                        'anyof',
                        ['type', 'f'],
                        ['type', 'd'],
                    ],
                    'fields' => [
                        'name','type',
                    ],
                ]
            ]);
            $this->logger->debug(sprintf('Watchman: %s', $payload));
            yield $process->getStdin()->write($payload);

            if (!$process->isRunning()) {
                throw new WatcherDied(sprintf(
                    'Could not start process: %s',
                    $process->getCommand()
                ));
            }

            return $process;
        });
    }

    /**
     * @return Promise<string|null>
     */
    private function readJson(): Promise
    {
        return call(function () {
            $line = yield $this->lineReader->readLine();
            $this->logger->debug($line);

            if (null !== $line) {
                return $line;
            }

            $exitCode = yield $this->process->join();
        
            // probably ran out of watchers, throw an error which can be
            // handled downstream.
            if ($exitCode === 1) {
                throw new WatcherDied(sprintf(
                    'Inotify exited with status code "%s": %s',
                    $exitCode,
                    yield buffer($this->process->getStderr())
                ));
            }

            return null;
        });
    }
}
