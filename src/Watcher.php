<?php

namespace Phpactor\AmpFsWatch;

interface Watcher
{
    /**
     * @param array<string> $paths
     */
    public function watch(array $paths): WatcherProcess;

    public function isSupported(): bool;
}
