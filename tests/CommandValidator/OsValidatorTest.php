<?php

namespace Phpactor\AmpFsWatcher\Tests\CommandValidator;

use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;

class OsValidatorTest extends TestCase
{
    public function testIsWindows(): void
    {
        self::assertTrue((new OsDetector('Windows NT Foo'))->isWindwos());
        self::assertFalse((new OsDetector('Windows'))->isMac());
    }

    public function testIsLinux(): void
    {
        self::assertTrue((new OsDetector('Linux'))->isLinux());
    }

    public function testIsMac(): void
    {
        self::assertTrue((new OsDetector('Macintosh'))->isMac());
    }
}
