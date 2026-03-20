<?php
namespace Lyte\Serial;
class Serial {
	public static function unserialize($string) {
		return (new Unserializer($string))->unserialize();
	}

	public static function isSerialized($string) {
		try {
			self::unserialize($string);
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * Rewrite incorrect s:N length prefixes using nested / quote-scan inference.
	 *
	 * @return array{0: string, 1: array<int, array<string, mixed>>} [repaired, lengthIssues]
	 */
	public static function repairSerializedString($string) {
		return Unserializer::repairSerializedString($string);
	}
}
