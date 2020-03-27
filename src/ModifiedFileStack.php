<?php

namespace Phpactor\AmpFsWatch;

class ModifiedFileStack
{
    /**
     * @var array<ModifiedFile>
     */
    private $stack;

    /**
     * @param array<ModifiedFile> $stack
     */
    public function __construct(array $stack = [])
    {
        $this->stack = $stack;
    }

    public function append(ModifiedFile $file): void
    {
        $this->stack[] = $file;
    }

    public function compress(): self
    {
        $new = [];
        foreach ($this->stack as $file) {
            $new[$file->path()] = $file;
        }

        return new self(array_values($new));
    }

    public function unshift(): ?ModifiedFile
    {
        return array_shift($this->stack);
    }

    public function toArray(): array
    {
        return $this->stack;
    }
}
