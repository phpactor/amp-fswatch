<?php

namespace Phpactor\AmpFsWatch;

use Amp\Promise;

interface Watcher
{
    /**
     * @param array<string> $paths
     * @return Promise<WatcherProcess>
     */
    public function watch(array $paths): Promise;

    public function isSupported(): bool;
}
