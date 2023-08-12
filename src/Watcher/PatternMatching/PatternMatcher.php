<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Webmozart\Glob\Glob;

class PatternMatcher
{
    public function matches(string $path, string $pattern): bool
    {
        return Glob::match($path, $pattern);
    }
}
