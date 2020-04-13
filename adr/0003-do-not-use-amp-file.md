Not using AMP file for PHP polling
==================================

Context
-------

The PHP poll is the last resort for tracking modifications. Initially
it was thought that using the [AMP file](https://github.com/amphp/file)
abstraction would provide a better non-blocking solution.

The performance is significantly worst than using PHPs native, blocking, file
functions in this case (e.g. 5 seconds to scan 20K files vs 1 second).

Decision
--------

Use the PHP native file functions, but still yield control to the event loop
for each directory traversed.

Consequences
------------

There might possibly be noticeable blocking.
