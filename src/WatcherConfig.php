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
     * @var array<string>
     */
    private $filePatterns;

    /**
     * @param array<string> $paths
     * @param array<string> $filePatterns
     */
    public function __construct(array $paths, int $pollInterval = 1000, array $filePatterns = [])
    {
        $this->paths = $paths;
        $this->pollInterval = $pollInterval;
        $this->filePatterns = $filePatterns;
    }

    public function withPath(string $path): self
    {
        $new = clone $this;
        $new->paths[] = $path;
        return $new;
    }

    public function withPollInterval(int $pollInterval): self
    {
        $new = clone $this;
        $new->pollInterval = $pollInterval;
        return $new;
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

    /**
     * @return array<string>
     */
    public function filePatterns(): array
    {
        return $this->filePatterns;
    }
}
