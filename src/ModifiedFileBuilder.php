<?php

namespace Phpactor\AmpFsWatch;

class ModifiedFileBuilder
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $type = ModifiedFile::TYPE_FILE;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function fromPathSegments(string ...$segments): self
    {
        return new self(implode('/', $segments));
    }

    public static function fromPath(string $path): self
    {
        return new self($path);
    }

    public function asFile(): self
    {
        $this->type = ModifiedFile::TYPE_FILE;
        return $this;
    }

    public function asFolder(): self
    {
        $this->type = ModifiedFile::TYPE_FOLDER;
        return $this;
    }

    public function build(): ModifiedFile
    {
        return new ModifiedFile($this->path, $this->type);
    }
}
