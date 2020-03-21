<?php

namespace Phpactor\AmpFsWatch\Watcher\Inotify;

use RuntimeException;

class InotifyEvent
{
    /**
     * @var string
     */
    private $watchedFileName;

    /**
     * @param array<string> $eventNames
     */
    private $eventNames;

    /**
     * @var string
     */
    private $eventFilename;

    /**
     * @param array<string> $eventNames
     */
    public function __construct(string $watchedFileName, array $eventNames, ?string $eventFilename)
    {
        $this->watchedFileName = $watchedFileName;
        $this->eventNames = $eventNames;
        $this->eventFilename = $eventFilename;
    }

    public static function createFromCsv(string $line): self
    {
        $parts = str_getcsv($line);

        if (count($parts) !== 3) {
            throw new RuntimeException(sprintf(
                'Could not parse inotify output "%s"',
                $line
            ));
        }

        [$watchedFileName, $eventNames, $eventFilename] = str_getcsv($line);
        $eventNames = explode(',', $eventNames);

        return new self($watchedFileName, $eventNames, $eventFilename);
    }

    public function hasEventName(string $name): bool
    {
        return in_array($name, $this->eventNames);
    }

    public function eventFilename(): string
    {
        return $this->eventFilename;
    }

    /**
     * @return array<string>
     */
    public function eventNames(): array
    {
        return $this->eventNames;
    }

    public function watchedFileName(): string
    {
        return $this->watchedFileName;
    }
}
