<?php

namespace Phpactor\AmpFsWatch;

use Amp\Promise;

interface Watcher
{
    /**
     * @return Promise<WatcherProcess>
     */
    public function watch(): Promise;

    /**
     * @return Promise<bool>
     */
    public function isSupported(): Promise;

    /**
     * @return string
     */
    public function describe(): string;
}
