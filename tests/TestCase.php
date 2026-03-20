<?php
namespace Lyte\Serial\Tests;
require_once(dirname(__DIR__).'/vendor/autoload.php');
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit\\Framework\\TestCase', 'PHPUnit_Framework_TestCase');
}
abstract class TestCase extends \PHPUnit_Framework_TestCase {
	/**
	 * PHPUnit 9+ compatibility shim (legacy tests use this name).
	 */
	public function setExpectedException($type, $message = '', $code = null) {
		$this->expectException($type);
		if ($message !== '') {
			$this->expectExceptionMessage($message);
		}
	}
}
