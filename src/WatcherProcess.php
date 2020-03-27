<?php

namespace Phpactor\AmpFsWatch;

use Amp\Promise;

interface WatcherProcess
{
    public function stop(): void;

    public function wait(): Promise;
}
