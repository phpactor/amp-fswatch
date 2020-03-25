<?php

namespace Phpactor\AmpFsWatch\SystemDetector;

class OsDetector
{
    /**
     * @var string
     */
    private $phpOs;

    public function __construct(string $phpOs)
    {
        $this->phpOs = $phpOs;
    }

    public function isLinux(): bool
    {
        return $this->phpOs === 'Linux';
    }

    public function isMac(): bool
    {
        return strtolower(substr($this->phpOs, 0, 3)) === 'mac';
    }

    public function isWindwos(): bool
    {
        return strtolower(substr($this->phpOs, 0, 3)) === 'win';
    }
}
