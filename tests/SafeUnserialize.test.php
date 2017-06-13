<?php

require_once(dirname(__DIR__).'/inc/SafeUnserialize.php');
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit\\Framework\\TestCase','PHPUnit_Framework_TestCase');
}
class TestLyteSafeUnserialize extends PHPUnit_Framework_TestCase {
	public function checkUnserialize($data) {
		$string = serialize($data);
		$serial = new LyteSafeUnserialize();
		$offset = 0;
		$stringUnserialized = $serial->_unserialize($string, $offset);
		$this->assertSame($data, $stringUnserialized);
		$this->assertSame(
			strlen($string),
			$offset,
			"$string"
		);
	}

	public function testNull() {
		$this->checkUnserialize(null);
	}

	public function testString() {
		$this->checkUnserialize("foo");
		$this->checkUnserialize("bar");
		$this->checkUnserialize("foobarbaz");
		$this->checkUnserialize("foobarbazfoobarbazfoobarbaz");
		$this->checkUnserialize("foo\x00bar");
	}

	public function testArray() {
		$this->checkUnserialize(array());
		$this->checkUnserialize(array(1));
		$this->checkUnserialize(array('foo'));
		$this->checkUnserialize(array(chr(0) => chr(0)));
	}

	public function testBool() {
		$this->checkUnserialize(true);
		$this->checkUnserialize(false);
	}

	public function testInt() {
		$this->checkUnserialize(1);
		$this->checkUnserialize(-42);
	}

	public function testDouble() {
		$this->checkUnserialize(1.23);
		$this->checkUnserialize(.987129837123);
		$this->checkUnserialize(-812673987612398761298736129367);
	}

	public function testComplex() {
		$this->checkUnserialize(array('foo' => array('bar')));
		$this->checkUnserialize(array('foo' => array(serialize('bar'))));
	}

	public function checkMalicious($str) {
		$serial = new LyteSafeUnserialize();
		$this->checkMethodThrows($serial, 'unserialize', array($str));
	}

	public function testMalicious() {
		$this->checkMalicious(serialize(new stdClass()));
		$this->checkMalicious(serialize(array(new stdClass())));
		$this->checkMalicious('C:8:"stdClass":0:{}');
		$this->checkMalicious('a:3:{}');
		$this->checkMalicious("N;\x00");
	}

	public function testExpect() {
		$that = $this;
		$checkExpectThrows = function($args) use ($that) {
			$serial = new LyteSafeUnserialize();
			// turn $offset in to a reference
			$offset = $args[1];
			$args[1] = &$offset;
			$that->checkMethodThrows(
				$serial,
				'expect',
				$args,
				"/^Unexpected character at /"
			);
		};
		$checkExpect = function($args, $expected) use ($that) {
			$serial = new LyteSafeUnserialize();
			$offset = $args[1];
			$serial->expect($args[0], $offset, $args[2]);
			$this->assertSame($expected, $offset);
		};

		$checkExpect(array('1234', 0, '123'), 3);
		$checkExpect(array('123', 2, '3'), 3);
		$checkExpect(array('123', 0, '12'), 2);
		$checkExpectThrows(array('12', 0, '2'));
		$checkExpectThrows(array('12', 0, '23'));
	}

	public function checkMethodThrows($object, $method, $args, $exceptionRe = '/./') {
		$caught = false;
		try {
			call_user_func_array(array($object, $method), $args);
		} catch (Exception $e) {
			$caught = true;
			$this->assertRegExp($exceptionRe, $e->getMessage());
		}
		$this->assertTrue($caught);
	}

	public function testLength() {
		$serial = new LyteSafeUnserialize();
		$that = $this;
		$checkValidLength = function($data, $offset, $expectedLength, $expectedOffset) use ($that, $serial) {
			$length = $serial->getLength($data, $offset);
			$this->assertSame($expectedLength, $length);
			$this->assertSame($expectedOffset, $offset);
		};
		$checkValidLength('1:', 0, 1, 1);
		$checkValidLength('12:', 0, 12, 2);
		$checkValidLength('12345:', 1, 2345, 5);

		$checkInvalidLength = function($data, $offset) use ($that, $serial) {
			$that->checkMethodThrows($serial, 'getLength', array($data, &$offset), '/Unable to determine length/');
		};

		$checkInvalidLength('1', 0);
		$checkInvalidLength(':1', 1);
		$checkInvalidLength('1234123', 1);
	}

	public function testGetType() {
		$that = $this;
		$checkGetType = function($data, $expectedType, $offset = 0, $expectedOffset = 1) use ($that) {
			$serial = new LyteSafeUnserialize();
			$type = $serial->getType($data, $offset);
			$this->assertSame($expectedType, $type);
			$this->assertSame($expectedOffset, $offset);
		};
		$checkGetType('a:', 'array');
		$checkGetType('Xa:', 'array', 1, 2);
		$checkGetType('N;', 'null');
		$checkGetType('s:', 'string');

		$serial = new LyteSafeUnserialize();
		$offset = 0;
		$this->checkMethodThrows($serial, 'getType', array('O:', &$offset), "/^Unhandled type 'O'$/");
	}

	public function testMultibyte() {
		$this->checkUnserialize("😄👏🎆");
		$this->checkUnserialize("你好世界");
		$this->checkUnserialize("\x93bendy quotes\x94");
	}
}