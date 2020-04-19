<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\PatternMatching;

use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\Watcher\PatternMatching\PatternMatcher;

class PatternMatcherTest extends TestCase
{
    /**
     * @dataProvider provideMatchesPattern
     */
    public function testMatchesPattern(string $path, string $pattern, bool $expected)
    {
        self::assertEquals($expected, (new PatternMatcher())->matches($path, $pattern));
    }

    public function provideMatchesPattern()
    {
        yield [
            '/foobar',
            '',
            false
        ];

        yield [
            '/foobar',
            '/foobar',
            true
        ];

        yield [
            '/foobar',
            '/*foobar',
            true
        ];

        yield [
            '/foobar/foobar',
            '/foobar/bar*',
            false
        ];

        yield [
            '/barfoo/foobar.php',
            '/**/*.php',
            true
        ];

        yield [
            '/foobar.php',
            '/**/*.php',
            true
        ];
    }
}
