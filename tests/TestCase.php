<?php
namespace Lyte\Serial\Tests;
require_once(dirname(__DIR__).'/vendor/autoload.php');
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit\\Framework\\TestCase','PHPUnit_Framework_TestCase');
}
abstract class TestCase extends \PHPUnit_Framework_TestCase {
	/**
	 * PHPUnit compatibility shim
	 */
	public function setExpectedException($type, $message = '', $code = null) {
		if (is_callable('parent::setExpectedException')) {
			return parent::setExpectedException($type, $message);
		}
		$this->expectException($type);
		if (!empty($message))
			$this->expectExceptionMessage($message);
	}
}