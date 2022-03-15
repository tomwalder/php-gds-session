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

### Optional Configuration ###

You may want to set a `\Memcached` instance, for improved performance
```php
$mc = new Memcached('mc');
$mc->addServer('127.0.0.1', 11211);
GDS\Session\Handler::setMemcached($mc);
```

You may want to supply your own GDS Gateway instance, configured to point at the correct project with the right protocol (gRPC / REST etc.).

If you do NOT supply your own, the default is "REST" and against the current Google Cloud project. [More details here](https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php#L82).

```php
$gw = new \GDS\Gateway\GRPCv1('my-project-id');
GDS\Session\Handler::setGateway($gw);
```

### All Together Now ###
Your setup might look like this with Memcached as custom Gateway. It's recommended to call `::start()` last.
```php
// ... configure Memcached & Gateway
GDS\Session\Handler::setMemcached($mc);
GDS\Session\Handler::setGateway($gw);
GDS\Session\Handler::start();
```


## Installation ##

### Composer ###

To install using Composer, use this require line, for production

`"tomwalder/php-gds-session": "^3.0"`

## Session Duration ##

By default, the handler uses 1 day (86,400 seconds) for session duration.

You can set your own custom duration by passing it in to the `start()` method, like this:

```php
GDS\Session\Handler::start(3600);
```

## Demo ##

https://gds-session-demo.appspot.com
