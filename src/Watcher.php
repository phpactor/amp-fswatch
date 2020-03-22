<?php

namespace Phpactor\AmpFsWatch;

interface Watcher
{
    /**
     * @param array<string> $paths
     */
    public function monitor(array $paths, callable $callback): void;

    public function stop(): void;
}
