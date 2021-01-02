Amp FS Watch
============

![CI](https://github.com/phpactor/amp-fswatch/workflows/CI/badge.svg)

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

See `bin/watch` for an implementation, which looks _something_ like this:

```php
Loop::run(function () use () {

    $logger = // create a PSR logger
    $config = new WatcherConfig([$path]);
    $watcher = new PatternMatchingWatcher(
        new FallbackWatcher([
            new BufferedWatcher(new InotifyWatcher($config, $logger), 10),
            new FindWatcher($config, $logger),
            new PhpPollWatcher($config, $logger),
            new FsWatchWatcher($config, $logger)
        ], $logger),
        [ '/**/*.php' ],
        []
    );

    $process = yield $watcher->watch([$path]);

    while (null !== $file = yield $process->wait()) {
        fwrite(STDOUT, sprintf('[%s] %s (%s)'."\n", date('Y-m-d H:i:s.u'), $file->path(), $file->type()));
    }
});
```
### Watchman

[Watchman](https://facebook.github.io/watchman/) needs to be installed and
will work on Linux, Mac and Windows.

```php
use Phpactor\AmpFsWatch\Watcher\Watchman\WatchmanWatcher;

$watcher = new WatchmanWatcher($config, $logger);
```

### Inotify

Use the Linux `inotifywait` binary to monitor for changes.

```php
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;

$watcher = new InotifyWatcher($config, $logger);
// ...
```

### Fswatch

**Unstable**: This watcher has not been extensively tested.

[FsWatch](https://github.com/emcrisostomo/fswatch) is a cross-platform
(Linux,Mac,Windows) file watching utility which will automatically use the
platforms native functionality when possible.

```php
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;

$watcher = new FsWatchWatcher($config, $logger);
// ...
```

### Find

Use the `find` binary (Linux and Mac) to poll for file changes.

Poll for changes every second:

```php
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;

$watcher = new FindWatcher($config, $logger);
// ...
```

Note that while this should work on GNU and BSD variants of `find` it may not
work on other variants due to being invoked with `-newerxy` switch, which is
not in the POSIX standard.

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

Contributing
------------

This package is open source and welcomes contributions! Feel free to open a
pull request on this repository.

Support
-------

- Create an issue on the main [Phpactor](https://github.com/phpactor/phpactor) repository.
- Join the `#phpactor` channel on the Slack [Symfony Devs](https://symfony.com/slack-invite) channel.

