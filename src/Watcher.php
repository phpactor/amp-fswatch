<?php

namespace Phpactor\AmpFsWatch;

interface Watcher
{
    public function monitor(callable $callback): void;
}
