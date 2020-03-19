<?php

namespace Phpactor\AmpFsWatch;

class FileModification
{
    /**
     * @var string
     */
    private $watchedFilename;

    /**
     * @var string
     */
    private $eventNames;
    /**
     * @var string|null
     */
    private $eventFilename;

    public function __construct(string $watchedFilename, string $eventNames, ?string $eventFilename = null)
    {
        $this->watchedFilename = $watchedFilename;
        $this->eventNames = $eventNames;
        $this->eventFilename = $eventFilename;
    }

    public static function fromCsvString(string $read): self
    {
        $read = str_getcsv($read);
        return new self(...$read);
    }

    public function watchedFilename(): string
    {
        return $this->watchedFilename;
    }

    public function eventNames(): string
    {
        return $this->eventNames;
    }

    public function eventFilename(): string
    {
        return $this->eventFilename;
    }
}
