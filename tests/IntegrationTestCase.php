<?php

namespace Phpactor\AmpFsWatcher\Tests;

use Amp\PHPUnit\AsyncTestCase;
use Phpactor\TestUtils\Workspace;

class IntegrationTestCase extends AsyncTestCase
{
    protected function workspace(): Workspace
    {
        return Workspace::create(__DIR__ . '/Workspace');
    }
}
