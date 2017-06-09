# PHP Lyte Serial

PHP Serialized string array and scalar unserialization using pure PHP.

# Why?

The standard `serialize()` and `unserialize()` calls in PHP are known to be unsafe even if you use the `$allowed_classes` filter in PHP 7 (there are memory corruption bugs).

The standard answer to this is "use JSON" but some applications were using PHP serialized strings long for internal storage long before JSON was a thing (well, at least popular).

In this case it may be useful to have a safer parser that rejects anything that's not an array or scalar type (i.e what you could safely store in JSON) as a middle ground to harden a code base without having to immediately switch out the underlying storage format.