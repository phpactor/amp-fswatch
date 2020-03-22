<?php

namespace Phpactor\AmpFsWatcher\Tests;

use Amp\PHPUnit\AsyncTestCase;
use PHPUnit\Framework\TestCase;
use Phpactor\TestUtils\Workspace;

class IntegrationTestCase extends AsyncTestCase
{
    protected function workspace(): Workspace
    {
        return Workspace::create(__DIR__ . '/Workspace');
    }
}
