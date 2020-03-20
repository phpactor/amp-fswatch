<?php

namespace Phpactor\AmpFsWatch;

interface Watcher
{
    public function start(): void;

    public function monitor(callable $callback): void;
}
