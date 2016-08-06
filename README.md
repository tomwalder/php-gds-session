# Google AppEngine Datastore Session Handler for PHP #

Google App Engine uses Memcache to store session data by default (specifically the Standard PHP Runtime).

This is bad. It means that session data disappears from time to time, as the shared Memcache is rotated. This will log out any signed in users (as well as lose any session data).

This library provides a Datastore + Memcache alternative session handler. It means that your session data is persisted, not just kept in memory.

Allowing you to have

* More reliable user sign in (less random-log-outs)
* Much longer sessions

## Example Usage ##

You need to do this somewhere early in your application code.

```php
GDS\Session\Handler::start();
```

That's it!

This will replace the default Memcache session handler with a shiny new one.  This method does call `session_start()` so make sure you're not doing that too!

## Installation ##

### Composer ###

To install using Composer, use this require line, for production

`"tomwalder/php-gds-session": "v1.0.0"`

## Session Duration ##

By default, the handler uses 1 day (86,400 seconds) for session duration.

You can set your own custom duration by passing it in to the `start()` method, like this:

```php
GDS\Session\Handler::start(3600);
```

## Demo ##

https://gds-session-demo.appspot.com
