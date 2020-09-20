<?php

namespace Phpactor\AmpFsWatch\Watcher\Watchman;

use Amp\ByteStream\LineReader;
use Amp\Process\Process;
use Amp\Promise;
use Phpactor\AmpFsWatch\Exception\WatcherDied;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use function Amp\ByteStream\buffer;
use function Amp\Promise\first;
use function Amp\call;

class WatchmanWatcher implements Watcher, WatcherProcess
{
    const WATCHMAN_CMD = 'watchman';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Process[]
     */
    private $subscribers = [];

    /**
     * @var CommandDetector
     */
    private $commandDetector;

    /**
     * @var WatcherConfig
     */
    private $config;

    /**
     * @var LineReader[]
     */
    private $lineReaders = [];

    /**
     * @var array<int,Promise<string|null>>
     */
    private $lineReaderPromises = [];

    /**
     * @var array<ModifiedFile>
     */
    private $fileBuffer = [];

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->config = $config;
    }

    public function watch(): Promise
    {
        return call(function () {
            yield $this->watchPaths();

            foreach ($this->config->paths() as $path) {
                $subscriber = yield $this->subscribe($path);
                $this->subscribers[] = $subscriber;
                $this->lineReaders[] = new LineReader($subscriber->getStdout());
            }

            return $this;
        });
    }

    public function wait(): Promise
    {
        return call(function () {
            while (null !== $file = array_shift($this->fileBuffer)) {
                return $file;
            }

            while (null !== $line = yield $this->readLine()) {
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
                $this->fileBuffer = array_merge($this->fileBuffer, $files);

                return $file;
            };
        });
    }

    public function stop(): void
    {
        if (null === $this->subscribers) {
            throw new RuntimeException(
                'Inotifywait process was not started, cannot call stop()'
            );
        }

        foreach ($this->subscribers as $subscriber) {
            $subscriber->signal(SIGTERM);
        }
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
                    'watch',
                    $path
                ]);


                $pid = yield $process->start();
                $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));
                $exit = yield $process->join();

                if ($exit !== 0) {
                    throw new RuntimeException(sprintf(
                        'Watchman exited with code "%s": %s ',
                        $exit,
                        yield buffer($process->getStderr())
                    ));
                }
            }
        });
    }

    public function isSupported(): Promise
    {
        return $this->commandDetector->commandExists(self::WATCHMAN_CMD);
    }

    /**
     * @return Promise<Process>
     */
    private function subscribe(string $path): Promise
    {
        return call(function () use ($path) {
            $process = new Process([
                self::WATCHMAN_CMD,
                '-j',
                '-p',
                '--no-pretty'
            ]);
            $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));

            $pid = yield $process->start();
            $payload = (string)json_encode([
                'subscribe',
                $path,
                'ampfs-watch',
                [
                    'expression' => [
                        'allof',
                        [
                            'anyof',
                            ['type', 'f'],
                            ['type', 'd']
                        ],
                        [
                            'since',
                            time(),
                            'ctime'
                        ],
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
    private function readLine(): Promise
    {
        return call(function () {
            foreach ($this->lineReaders as $index => $lineReader) {
                if (array_key_exists((int)$index, $this->lineReaderPromises)) {
                    continue;
                }

                $this->lineReaderPromises[(int)$index] = call(function (int $index, LineReader $lineReader) {
                    $line = yield $lineReader->readLine();
                    return [$index, $line];
                }, $index, $lineReader);
            }

            [$index, $line] = yield first($this->lineReaderPromises);
            unset($this->lineReaderPromises[(int)$index]);
            $this->logger->debug(print_r($line, true));

            if (null !== $line) {
                return $line;
            }

            foreach ($this->subscribers as $subscriber) {
                if ($subscriber->isRunning()) {
                    continue;
                }
                $exitCode = yield $subscriber->join();

                // probably ran out of watchers, throw an error which can be
                // handled downstream.
                if ($exitCode === 1) {
                    throw new WatcherDied(sprintf(
                        'Watchman subscriber exited with status code "%s": %s',
                        $exitCode,
                        yield buffer($subscriber->getStderr())
                    ));
                }
            }

            return null;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'watchman';
    }
}
