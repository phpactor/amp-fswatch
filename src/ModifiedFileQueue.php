<?php

namespace Phpactor\AmpFsWatch;

class ModifiedFileQueue
{
    /**
     * @var array<ModifiedFile>
     */
    private $queue;

    /**
     * @param array<ModifiedFile> $queue
     */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function enqueue(ModifiedFile $file): void
    {
        array_unshift($this->queue, $file);
    }

    public function compress(): self
    {
        $new = [];
        foreach ($this->queue as $file) {
            $new[$file->path()] = $file;
        }

        return new self(array_values($new));
    }

    public function dequeue(): ?ModifiedFile
    {
        return array_pop($this->queue);
    }
}
