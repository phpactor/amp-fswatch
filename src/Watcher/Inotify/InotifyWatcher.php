<?php

namespace Phpactor\AmpFsWatch\Watcher\Inotify;

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
use function Amp\ByteStream\buffer;
use Webmozart\PathUtil\Path;

class InotifyWatcher implements Watcher, WatcherProcess
{
    const INOTIFY_CMD = 'inotifywait';

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
        return \Amp\call(function () {
            $this->process = yield $this->startProcess();
            $this->lineReader = new LineReader($this->process->getStdout());

            return $this;
        });
    }

    public function wait(): Promise
    {
        return \Amp\call(function () {
            while (null !== $file = array_shift($this->directoryBuffer)) {
                return $file;
            }

            if (null === $line = yield $this->lineReader->readLine()) {
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
            }

            $event = InotifyEvent::createFromCsv($line);

            $builder = ModifiedFileBuilder::fromPathSegments(
                $event->watchedFileName(),
                $event->eventFilename()
            );

            if ($event->hasEventName('ISDIR')) {
                $builder = $builder->asFolder();
            }

            $modifiedFile = $builder->build();

            if ($event->hasEventName('MOVED_TO') && $modifiedFile->type() === ModifiedFile::TYPE_FOLDER) {
                yield $this->enqueueDirectory($modifiedFile->path());
            }

            return $modifiedFile;
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
     * @return Promise<Process>
     */
    private function startProcess(): Promise
    {
        return \Amp\call(function () {
            $process = new Process(array_merge([
                self::INOTIFY_CMD,
                '-r',
                '-emodify,create,delete,move',
                '--monitor',
                '--csv',
            ], $this->config->paths()));

            $pid = yield $process->start();
            $this->logger->debug(sprintf('Started "%s"', $process->getCommand()));

            if (!$process->isRunning()) {
                throw new WatcherDied(sprintf(
                    'Could not start process: %s',
                    $process->getCommand()
                ));
            }

            return $process;
        });
    }

    public function isSupported(): Promise
    {
        if (!$this->osDetector->isLinux()) {
            return new Success(false);
        }

        return $this->commandDetector->commandExists(self::INOTIFY_CMD);
    }

    /**
     * @return Promise<void>
     */
    private function enqueueDirectory(string $path): Promise
    {
        return \Amp\call(function () use ($path) {
            $files = scandir($path);
            foreach ((array)$files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = Path::join($path, $file);
                $isDir = is_dir($filePath);
                $file = ModifiedFileBuilder::fromPath($filePath);

                if ($isDir) {
                    $file = $file->asFolder();
                }

                $this->directoryBuffer[] = $file->build();

                if ($isDir) {
                    yield $this->enqueueDirectory($filePath);
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'inotify';
    }
}
