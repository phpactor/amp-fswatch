<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use DateTimeImmutable;
use Phpactor\AmpFsWatch\Watcher;

class FindWatcher implements Watcher
{
    /**
     * @var DateTimeImmutable
     */
    private $start;

    public function monitor(callable $callback): void
    {
        $this->start = new DateTimeImmutable();
    }
}
