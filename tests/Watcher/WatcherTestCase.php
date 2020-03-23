<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Amp\Loop;
use Amp\Promise;
use Closure;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;

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
            $process = $watcher->monitor($paths, function (ModifiedFile $modification) use (&$modifications) {
                $modifications[] = $modification;
            });
            
            $generator = $plan();

            yield from $generator;
            $process->stop();

            return $modifications;
        });
    }

    abstract protected function createWatcher(): Watcher;
}
