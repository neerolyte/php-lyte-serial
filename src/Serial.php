<?php
namespace Lyte\Serial;
class Serial {
	public static function unserialize($string) {
		return (new Unserializer($string))->unserialize();
	}

	public static function isSerialized($string) {
		try {
			self::unserialize($string);
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}
}
