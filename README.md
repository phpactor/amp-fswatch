Amp FS Watch
============

[![Build Status](https://travis-ci.org/phpactor/amp-fswatch.svg?branch=master)](https://travis-ci.org/phpactor/amp-fswatch)

This is asynchronously (via. [Amp](https://amphp.org/)) monitor paths on your
file system using various stategues.

It's designed to trigger code indexing in
[Phpactor](https://github.com/phpactor/phpactor).

Usage
-----

In general:

```php
use Amp\Loop;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Psr\Log\NullLogger;

$logger = new NullLogger();

$watcher = new InotifyWatcher($logger);
$process = $watcher->watch([ 'src' ], function (ModifiedFile $file) {
    // do something
});

Loop::run();
```

### Inotify

Use the Linux `inotifywait` binary to monitor for changes.

```php
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;

$watcher = new InotifyWatcher($logger);
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
