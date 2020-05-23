Amp FS Watch
============

[![Build Status](https://travis-ci.org/phpactor/amp-fswatch.svg?branch=master)](https://travis-ci.org/phpactor/amp-fswatch)

This is an [Amp](https://amphp.org/) library for asynchronously monitor paths
on your file system changes using various stategues.

It's been created to trigger code indexing in
[Phpactor](https://github.com/phpactor/phpactor).

- Promise based API.
- Capable of automatically selecting a supported watcher for the current
  environment.
- Provides realtime (e.g. ``inotify``) watchers in addition to polling ones.
- Provides decorators for:
  - Including / excluding patterns.
  - Buffering notifications.
- Unitifed configuration for all watchers.

Usage
-----

In general (see `bin/watch` for a simple example implementation):

```php
use Amp\Loop;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher\Fallback\FallbackWatcher;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatch\Watcher\PhpPollWatcher\PhpPollWatcher;

$logger = // create a PSR logger
Loop::run(function () use () {
    $watcher = new FallbackWatcher([
        new InotifyWatcher($config, $logger),
        new FsWatchWatcher($config, $logger),
        new FindWatcher($config, $logger),
        new PhpPollWatcher($config, $logger),
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

$watcher = new InotifyWatcher($config, $logger);
// ...
```

### Fswatch

[FsWatch](https://github.com/emcrisostomo/fswatch) is a cross-platform
(Linux,Mac,Windows) file watching utility which will automatically use the
platforms native functionality when possible.

```php
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;

$watcher = new FsWatchWatcher($config, $logger);
// ...
```

### Find

Use the POSIX `find` binary (Linux and Mac) to poll for file changes.

Poll for changes every second:

```php
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;

$watcher = new FindWatcher($config, $logger);
// ...
```

### PHP Poll

This is the slowest and most resource intensive option but it should
work on all environments.

```php
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;

$watcher = new PhpPollWatcher($config, $logger);
// ...
```

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
