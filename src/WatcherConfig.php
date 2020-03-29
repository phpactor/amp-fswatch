<?php

namespace Phpactor\AmpFsWatch;

class WatcherConfig
{
    /**
     * @var array<string>
     */
    private $paths;

    /**
     * @var int
     */
    private $pollInterval;

    /**
     * @param array<string> $paths
     */
    public function __construct(array $paths, int $pollInterval = 1000)
    {
        $this->paths = $paths;
        $this->pollInterval = $pollInterval;
    }

    /**
     * @return array<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    public function pollInterval(): int
    {
        return $this->pollInterval;
    }
}
