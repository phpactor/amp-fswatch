<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

class PatternMatcher
{
    private const WILDCARD_TOKEN = 'pah7peiD__WILDCARD__aevo7Aim';

    public function matches(string $path, string $pattern): bool
    {
        if (empty($pattern)) {
            return true;
        }

        $pattern = str_replace('*', self::WILDCARD_TOKEN, $pattern);
        $pattern = preg_quote($pattern);
        $pattern = str_replace(self::WILDCARD_TOKEN, '.*', $pattern);
        $pattern = sprintf('{^%s$}', $pattern);

        return 1 === preg_match($pattern, $path);
    }
}
