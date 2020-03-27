<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Promise;
use Closure;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileStack;
use Phpactor\AmpFsWatch\Watcher;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;
use Throwable;

abstract class WatcherTestCase extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace()->reset();
    }

    protected function createLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, $message, array $context = [])
            {
                fwrite(STDERR, sprintf('[%s] [%s] %s', microtime(), $level, $message)."\n");
            }
        };
    }

    protected function monitor(array $paths, Closure $plan): Promise
    {
        return \Amp\call(function () use ($paths, $plan) {
            $watcher = $this->createWatcher();
            $modifications = [];
            $process = $watcher->watch($paths);
            $results = new ModifiedFileStack();
            
            \Amp\asyncCall(function () use ($process, &$results) {
                while (null !== $file = yield $process->wait()) {
                    $results->append($file);
                }
            });

            $generator = $plan();

            yield from $generator;

            $process->stop();

            return $results->compress()->toArray();
        });
    }

    abstract protected function createWatcher(): Watcher;
}
