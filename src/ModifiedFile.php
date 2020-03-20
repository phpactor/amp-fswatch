<?php

namespace Phpactor\AmpFsWatch;

use Webmozart\PathUtil\Path;

class ModifiedFile
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        $this->path = Path::canonicalize($path);
    }

    public function path(): string
    {
        return $this->path;
    }
}
