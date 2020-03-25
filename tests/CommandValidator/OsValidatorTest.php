<?php

namespace Phpactor\AmpFsWatcher\Tests\CommandValidator;

use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\CommandValidator\OsValidator;

class OsValidatorTest extends TestCase
{
    public function testIsWindows(): void
    {
        self::assertTrue((new OsValidator('Windows NT Foo'))->isWindwos());
        self::assertFalse((new OsValidator('Windows'))->isMac());
    }

    public function testIsLinux(): void
    {
        self::assertTrue((new OsValidator('Linux'))->isLinux());
    }

    public function testIsMac(): void
    {
        self::assertTrue((new OsValidator('Macintosh'))->isMac());
    }
}
