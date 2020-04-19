<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Webmozart\Glob\Glob;

class PatternMatcher
{
    private const WILDCARD_TOKEN = 'pah7peiD__WILDCARD__aevo7Aim';

    public function matches(string $path, string $pattern): bool
    {
        return Glob::match($path, $pattern);
    }
}
