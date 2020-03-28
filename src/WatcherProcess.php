<?php

namespace Phpactor\AmpFsWatch;

use Amp\Promise;

interface WatcherProcess
{
    public function stop(): void;

    /**
     * @return Promise<?ModifiedFile>
     */
    public function wait(): Promise;
}
