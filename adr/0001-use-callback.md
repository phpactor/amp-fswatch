Using a callback in the public API
==================================

Context
-------

The public API uses a callback to intercept file modifications:

```php
$watcher->monitor($paths, function (ModifiedFile $file) {
   // do something
});
```

A promise based API would be better:

```php
$stream = $watcher->monitor($paths);
while (null !== $modifiedFile = yield $stream->wait()) {
    // do something
}
```

The problem is that the internals for this code are far more complicated as we
need to manage the backlog. Some of the strategies involve multiple processes
from which we need to read the streams.

So for example, for the find watcher we would need to maintain the state of
each of the find processes. As we can only return one promise at a time, and a
process may return multiple files at a time, we need to maintain a queue of
file modifications for the next call to `wait`.

Another simple Amp solution is to use an emitter, in which case the API would
be consumed as:

```php
$iterator = $watcher->watch($paths);

while (yield $iterator->advance()) {
    $modifiedFile = $iterator->getCurrent();
    // do something
}
```

This has about the same complexity as the callback solution, but the API
doesn't seem to be much better - and at least with the callback we can use a
type hint.

Decision
--------

Keep the simple callback interface.

Consequences
------------

It's not the best API, but it keeps the rest of the code simple and relatively
easy to understand.
