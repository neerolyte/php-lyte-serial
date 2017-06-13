<?php
namespace Lyte\Serial\Tests;
use Lyte\Serial\Unserializer;
require_once(dirname(__DIR__).'/inc/SafeUnserialize.php');
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit\\Framework\\TestCase','PHPUnit_Framework_TestCase');
}
class TestUnserializer extends \PHPUnit_Framework_TestCase {
	public function getDataForUnserializes() {
		return array(
			array(null),
			array("foo"),
			array("bar"),
			array("foobarbaz"),
			array("foobarbazfoobarbazfoobarbaz"),
			array("foo\x00bar"),
			array("😄👏🎆"),
			array("你好世界"),
			array("\x93bendy quotes\x94"),
			array(array()),
			array(array(1)),
			array(array('foo')),
			array(array(chr(0) => chr(0))),
			array(true),
			array(false),
			array(1),
			array(-42),
			array(1.23),
			array(.987129837123),
			array(-812673987612398761298736129367),
			array(array('foo' => array('bar'))),
			array(array('foo' => array(serialize('bar')))),
		);
	}

	/**
	 * @dataProvider getDataForUnserializes
	 */
	public function testUnserializes($data) {
		$string = serialize($data);
		$serial = new Unserializer($string);
		$actualData = $serial->unserialize();
		$this->assertSame($data, $actualData);
		$this->assertSame(
			strlen($string),
			$serial->offset,
			"$string"
		);
	}

	public function getDataForUnserializeFails() {
		return array(
			array('b:1', 'missing trailing ";" on boolean'),
			array("b:3;", "bools are only 1 or 0"),
			array("i:12-12;", "can't have minus sign in the middle of integer"),
			array(serialize(new \stdClass())),
			array(serialize(array(new \stdClass()))),
			array('C:8:"stdClass":0:{}'),
			array('a:3:{}', 'array with incorrect length'),
			array("N;\x00", 'trailing bytes'),
		);
	}

	/**
	 * @dataProvider getDataForUnserializeFails
	 */
	public function testUnserializeFails($string, $desc = null) {
		$serial = new Unserializer($string);
		$caught = false;
		try {
			$serial->unserialize();
		} catch (\Exception $e) {
			$caught = true;
		}
		$this->assertTrue($caught, $desc);
	}

	public function testCanNotInstantiateWithObject() {
		$this->setExpectedException('Exception', 'Data supplied for unserialization was not a string');
		new Unserializer(new \stdClass());
	}

	public function testUnserializeStdClass() {
		$this->setExpectedException('Exception', "Unhandled type 'O'");
		(new Unserializer(serialize(new \stdClass())))->unserialize();
	}

	public function getDataForExpectPassing() {
		return array(
			array('1234', '123', 0, 3),
			array('123', '3', 2, 3),
			array('123', '12', 0, 2),
		);
	}

	/**
	 * @param $data
	 * @param $chars
	 * @dataProvider getDataForExpectPassing
	 */
	public function testExpectPassing($data, $chars, $offset, $expectedOffset) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$serial->expect($chars);
		$this->assertSame($expectedOffset, $serial->offset);
	}

	public function getDataForExpectFailing() {
		return array(
			array('12', '2'),
			array('12', '123'),
		);
	}

	/**
	 * @dataProvider getDataForExpectFailing
	 */
	public function testExpectFailing($data, $chars) {
		$serial = new Unserializer($data);
		$this->setExpectedException('Exception');
		$serial->expect($chars);

	}

	public function getDataForGetLength() {
		return array(
			array('1:', 0, 1, 1),
			array('12:', 0, 12, 2),
			array('12345:', 1, 2345, 5),
		);
	}

	/**
	 * @dataProvider getDataForGetLength
	 */
	public function testGetLength($data, $offset, $expectedLength, $expectedOffset) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$length = $serial->getLength();
		$this->assertSame($expectedLength, $length);
		$this->assertSame($expectedOffset, $serial->offset);
	}

	public function getDataForInvalidLengths() {
		return array(
			array('1'),
			array(':1', 1),
			array('1234123', 1),
		);
	}

	/**
	 * @dataProvider getDataForInvalidLengths
	 */
	public function testInvalidLength($data, $offset = 0) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$this->setExpectedException('Exception', 'Unable to determine length');
		$serial->getLength();
	}

	public function getDataForGetTypes() {
		return array(
			array('a', 'array'),
			array('Xa', 'array', 1, 2),
			array('N', 'null'),
			array('s', 'string'),
			array('b', 'boolean'),
			array('i', 'integer'),
			array('d', 'double'),
		);
	}

	/**
	 * @dataProvider getDataForGetTypes
	 */
	public function testGetType($data, $expectedType, $offset = 0, $expectedOffset = 1) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$type = $serial->getType();
		$this->assertSame($expectedType, $type);
		$this->assertSame($expectedOffset, $serial->offset);
	}

	public function testGetTypeOfObject() {
		$this->setExpectedException('Exception', "Unhandled type 'O'");
		(new Unserializer('O:'))->unserialize();
	}

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