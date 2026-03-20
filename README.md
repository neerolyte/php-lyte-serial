# PHP Lyte Serial

[![Build Status](https://api.travis-ci.org/neerolyte/php-lyte-serial.svg?branch=master)](https://travis-ci.org/neerolyte/php-lyte-serial) [![Coverage Status](https://coveralls.io/repos/github/neerolyte/php-lyte-serial/badge.svg?branch=master)](https://coveralls.io/github/neerolyte/php-lyte-serial?branch=master)

PHP Serialized string array and scalar unserialization using pure PHP.

Runtime **PHP 7.3+** (aligned with PHPUnit 9 for development). The `composer.lock` is resolved for PHP 7.3 via `config.platform` so installs stay consistent on older runtimes.

# Usage

Install with composer:

```
composer require lyte/serial
```

## Serial

`Serial` is a simplified interface that attempts to work well in a legacy code
base.

Load the namespace:

```php
use Lyte\Serial\Serial;
// unserialize statically
$unserialized = Serial::unserialize($someSerializedString);
// or with an instance
$serial = new Serial;
$unserialized = $serial->unserialize($someSerializedString);

// check if a string appears to be serialized
if (Serial::isSerialized($someUnknownString)) {
	$unserialized = Serial::unserialize($someUnknownString);
}

// or rely on exceptions
try {
	$unserialized = Serial::unserialize($someUnknownString);
} catch (\Exception $e) {
	// ...
}
```

## Unserializer

`Unserializer` is the internal work horse.

```php
use Lyte\Serial\Unserializer;
$serial = new Unserializer($someSerializedString);
$unserialized = $serial->unserialize();
```

### Wrong `s:` byte lengths (nested payloads)

If a string declares `s:N:` but the next `"` is not exactly *N* bytes later (common when counts used UTF-8 lengths, wrong newlines, or copy-paste errors), strict parsing fails. Enable inference and optional wire repair:

```php
$u = new Unserializer($blob, ['repairLengths' => true]);
$data = $u->unserialize();
// $u->lengthIssues lists declared vs actual lengths and digit span offsets

list($fixedBlob, $issues) = Unserializer::repairSerializedString($blob);
// or Serial::repairSerializedString($blob)
```

`repairLengths` is read with `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)` and must coerce to **strict** true (e.g. `true`, `1`, `"1"`, `"true"`, `"on"`, `"yes"`). Values such as `false`, `0`, `"0"`, `"false"`, `"no"`, `""`, `null`, or unset leave repair **off** (so string `"false"` from env/JSON does not accidentally enable it).

For values that look like nested PHP serialization (`a:…`, `s:…`, etc.), the actual length is derived by parsing one full value. For other strings, repair uses the first `";` sequence with no `"` inside the payload (unsafe for arbitrary binary that may contain those bytes).

# Why?

The standard `serialize()` and `unserialize()` calls in PHP are known to be unsafe even if you use the `$allowed_classes` filter in PHP 7 (there are memory corruption bugs).

The standard answer to this is "use JSON" but some applications were using PHP serialized strings for internal storage long before JSON was a thing (well... popular).

In this case it may be useful to have a safer parser that rejects anything that's not an array or scalar type (i.e what you could safely store in JSON) as a middle ground to harden a code base without having to immediately switch out the underlying storage format.

Note: I'm not advocating letting any strings be unserialized that can in anyway be modified by a user, just that if you use a safer parser and someone compromises some other part of your application this _might_ at least slow them down.

## Why can't I use `$allowed_classes`?

PHP 7 added the `$allowed_classes` option to the [`unserialize()`](http://php.net/unserialize) function.

In theory you could just set this to `null` (or a safe set of classes), but unfortunately [there's memory corruption bugs](https://media.ccc.de/v/33c3-7858-exploiting_php7_unserialize) meaning if you rely on that behaviour, you **are** vulnerable.