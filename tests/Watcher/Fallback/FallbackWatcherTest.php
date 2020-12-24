<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Fallback;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;
use Generator;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Fallback\FallbackWatcher;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class FallbackWatcherTest extends AsyncTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    /**
     * @var ObjectProphecy|LoggerInterface
     */
    private $logger;

    /**
     * @var ObjectProphecy
     */
    private $watcher1;

    /**
     * @var ObjectProphecy
     */
    private $watcher2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->watcher1 = $this->prophesize(Watcher::class);
        $this->watcher1->describe()->willReturn('watcher1');
        $this->watcher2 = $this->prophesize(Watcher::class);
        $this->watcher2->describe()->willReturn('watcher2');
    }

    public function testNameIsUnknownWhenCalledBeforeInitialization()
    {
        $watcher = $this->createWatcher([
            $this->watcher1->reveal(),
            $this->watcher2->reveal(),
        ]);
        self::assertEquals('unknown (pending invocation)', $watcher->describe());
    }

    public function testUsesFirstSupportedWatcher()
    {
        $this->watcher1->isSupported()->willReturn(new Success(false));

        $callback = function () {
        };
        $paths = ['path1'];

        $nullWatcher = new NullWatcher();

        $watcher = $this->createWatcher([
            $this->watcher1->reveal(),
            $nullWatcher
        ]);
        $process = yield $watcher->watch($paths, $callback);

        self::assertSame($nullWatcher, $process);
        self::assertEquals('null', $watcher->describe());
    }

    public function testReturnsNullWatcherAndLogsWarningIfNoSupportedWatchers()
    {
        $this->watcher1->isSupported()->willReturn(new Success(false));
        $this->watcher2->isSupported()->willReturn(new Success(false));

        $callback = function () {
        };
        $paths = ['path1'];

        $process = yield $this->createWatcher([
            $this->watcher1->reveal(),
            $this->watcher2->reveal(),
        ])->watch($paths, $callback);

        $this->logger->warning(Argument::containingString('No supported watchers'))->shouldHaveBeenCalled();

        self::assertInstanceOf(NullWatcher::class, $process);
    }

    public function testIsAlwaysSupported(): Generator
    {
        $watcher = $this->createWatcher([]);
        self::assertTrue(yield $watcher->isSupported());
    }

    private function createWatcher(array $watchers): Watcher
    {
        return new FallbackWatcher(
            $watchers,
            $this->logger->reveal()
        );
    }
}
