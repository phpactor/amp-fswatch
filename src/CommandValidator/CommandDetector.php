<?php

namespace Phpactor\AmpFsWatch\CommandValidator;

use Amp\Process\Process;
use RuntimeException;

class CommandDetector
{
    public function commandExists(string $command): bool
    {
        return $this->checkPosixCommand($command);
    }

    private function checkPosixCommand(string $command): bool
    {
        return 0 === \Amp\Promise\wait(\Amp\call(function () use ($command) {
            $process = new Process([
                'command',
                '-v',
                $command
            ]);

            yield $process->start();

            return yield $process->join();
        }));
    }
}
