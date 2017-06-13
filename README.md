# PHP Lyte Serial

[![Build Status](https://api.travis-ci.org/neerolyte/php-lyte-serial.svg?branch=master)](https://travis-ci.org/neerolyte/php-lyte-serial)

PHP Serialized string array and scalar unserialization using pure PHP.

# Why?

The standard `serialize()` and `unserialize()` calls in PHP are known to be unsafe even if you use the `$allowed_classes` filter in PHP 7 (there are memory corruption bugs).

The standard answer to this is "use JSON" but some applications were using PHP serialized strings for internal storage long before JSON was a thing (well... popular).

In this case it may be useful to have a safer parser that rejects anything that's not an array or scalar type (i.e what you could safely store in JSON) as a middle ground to harden a code base without having to immediately switch out the underlying storage format.

Note: I'm not advocating letting any strings be unserialized that can in anyway be modified by a user, just that if you use a safer parser and someone compromises some other part of your application this _might_ at least slow them down.

## Why can't I use `$allowed_classes`?

PHP 7 added the `$allowed_classes` option to the [`unserialize()`](http://php.net/unserialize) function.

In theory you could just set this to `null` (or a safe set of classes), but unfortunately [there's memory corruption bugs](https://media.ccc.de/v/33c3-7858-exploiting_php7_unserialize) meaning if you rely on that behaviour, you **are** vulnerable.