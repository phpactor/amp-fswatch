Amp FS Watch
============

[![Build Status](https://travis-ci.org/phpactor/amp-fswatch.svg?branch=master)](https://travis-ci.org/phpactor/amp-fswatch)

This is an [Amp](https://amphp.org/) library for asynchronously monitor paths
on your file system changes using various stategues.

It's been created to trigger code indexing in
[Phpactor](https://github.com/phpactor/phpactor).

Usage
-----

In general:

```php
use Amp\Loop;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher\Fallback\FallbackWatcher;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;

Loop::run(function () use () {
    $watcher = new FallbackWatcher([
        new InotifyWatcher(),
        new FsWatchWatcher(),
        new FindWatcher(),
    ], $logger);

    $process = yield $watcher->watch([
        'src',
    ]);

    while (null !== $file = yield $process->wait()) {
        fwrite(STDOUT, sprintf('%s (%s)', $file->path(), $file->type()));
    }
});
```

### Inotify

Use the Linux `inotifywait` binary to monitor for changes.

```php
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;

$watcher = new InotifyWatcher($logger);
// ...
```

### Fswatch

[FsWatch](https://github.com/emcrisostomo/fswatch) is a cross-platform
(Linux,Mac,Windows) file watching utility which will automatically use the
platforms native functionality when possible.

```php
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;

$watcher = new FsWatchWatcher($logger);
// ...
```

### Find

Use the POSIX `find` binary (Linux and Mac) to poll for file changes.

Poll for changes every second:

```php
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;

$watcher = new FindWatcher(1000, $logger);
// ...
```

Arguments:

- 0: Milliseconds between polls

### Fallback

The fallback watcher will automatically select the first supported watcher
on the current system:

```php
use Phpactor\AmpFsWatch\Watcher\Fallback\FallbackWatcher;

$watcher = new FallbackWatcher(
    [
        new InotifyWatcher($logger),
        new FindWatcher(500, $logger)
    ]
    $logger
);
// ...
```
