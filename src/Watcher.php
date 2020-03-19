<?php

namespace Phpactor\AmpFsWatch;

use Amp\Promise;

interface Watcher
{
    /**
     * Return a promise with a FileModification object.
     */
    public function monitor(): Promise;
}
