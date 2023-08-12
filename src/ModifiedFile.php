<?php

namespace Phpactor\AmpFsWatch;

use Symfony\Component\Filesystem\Path;

class ModifiedFile
{
    const TYPE_FILE = 'file';
    const TYPE_FOLDER = 'folder';

    private string $path;

    private string $type;

    public function __construct(string $path, string $type)
    {
        $this->path = Path::canonicalize($path);
        $this->type = $type;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function type(): string
    {
        return $this->type;
    }
}
