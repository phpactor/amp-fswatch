<?php

namespace Phpactor\AmpFsWatch;

class SomeClass
{
    /**
     * @return Promise<string>
     */
    public function wait(): Promise
    {
        return call(function () {
            yield 'foobar';
            return 'barfoo';
        });
    }
}

/**
 * @template-covariant T
 */
class Promise
{
    /**
     * @var string
     */
    private $file;

    /**
     * @param mixed $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * @param callable(?\Throwable, ?TReturn):void $onResolved
     *
     * @return void
     */
    public function onResolve(callable $onResolved)
    {
    }
}

/**
 * @template TReturn
 * @param callable():(\Generator<mixed, mixed, mixed, TReturn>|TReturn) $callable
 * @return Promise<TReturn>
 */
function call(callable $callable)
{
    return new Promise($callable());
}
