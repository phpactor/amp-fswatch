<?php

namespace Phpactor\AmpFsWatch;

class WatcherConfig
{
    /**
     * @var array<string>
     */
    private $paths;

    /**
     * @param array<string> $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    /**
     * @return array<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }
}
