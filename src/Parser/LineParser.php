<?php

namespace Phpactor\AmpFsWatch\Parser;

use Amp\Process\ProcessInputStream;

class LineParser
{
    /**
     * @var string
     */
    private $buffer;

    public function stream(ProcessInputStream $stream, callable $callback): void
    {
        \Amp\asyncCall(function () use ($stream, $callback) {
            while (null !== $chunk = yield $stream->read()) {
                foreach (str_split($chunk) as $char) {
                    $this->buffer .= $char;

                    if ($char !== "\n") {
                        continue;
                    }

                    $line = $this->buffer;
                    $this->buffer = '';
                    $callback($line);
                }
            }
        });
    }
}
