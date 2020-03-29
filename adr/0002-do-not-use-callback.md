Do not use a callback in the public API
=======================================

Context
-------

In 0001 it was decided to use a callback to handle modified files. When used
with real code:

```php
$this->watcher->watch($this->paths, function (ModifiedFile $file) {
    asyncCall(function () use ($file) {
        $job = $this->indexer->getJob($file->path());

        foreach ($job->generator() as $file) {
            yield new Delayed(1);
        }
    });
});
```

Which is just odd. The callback is not part of the co-routine. Using a promise
improves this:

```php
while (null !== $file = yield $watcher->wait())
    $job = $this->indexer->getJob($file->path());

    foreach ($job->generator() as $file) {
        yield new Delayed(1);
    }
});
```

Decision
--------

Refactor the code to yield promises for modified files.

Whilst initially I thought this would be quite difficult, it didn't take long.
Each watcher has an async co-routing which builds a queue of modified files
which are then subsequently yieled when `->wait()` is called on the `Watcher`
(if there are no files, then we pause the co-routine for some milliseconds
then try again).

Consequences
------------

It should be easier to integrate this library into Amp projects. On the
downside it does mean coupling Amp to the public API - but seeing as this
package is called AmpFsWatch, that's acceptable.
