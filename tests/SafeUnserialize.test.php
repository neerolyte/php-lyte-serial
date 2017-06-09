<?php
require_once(dirname(__DIR__).'/inc/SafeUnserialize.php');
class TestLyteSafeUnserialize extends PHPUnit_Framework_TestCase {
	public function checkUnserialize($data) {
		$string = serialize($data);
		$serial = new LyteSafeUnserialise();
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
	}

	public function testArray() {
		$this->checkUnserialize(array());
		$this->checkUnserialize(array(1));
		$this->checkUnserialize(array('foo'));
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
		$serial = new LyteSafeUnserialise();
		$caught = false;
		try {
			$serial->unserialize($str);
		} catch (Exception $e) {
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testMalicious() {
		$this->checkMalicious(serialize(new stdClass()));
		$this->checkMalicious(serialize(array(new stdClass())));
		$this->checkMalicious('C:8:"stdClass":0:{}');
		$this->checkMalicious('a:3:{}');
	}

	public function testExpect() {
		$tests = array(
			array(
				'data' => '123',
				'offset' => 0,
				'expected' => '12',
				'result' => 2,
			),
			array(
				'data' => '123',
				'offset' => 2,
				'expected' => '3',
				'result' => 3,
			),
			array(
				'data' => '1234',
				'offset' => 0,
				'expected' => '234',
				'result' => 3,
			),
			array(
				'data' => '12',
				'offset' => 0,
				'expected' => '23',
				'result' => null,
			),
			array(
				'data' => '12',
				'offset' => 0,
				'expected' => '3',
				'result' => null,
			),
		);
		$serial = new LyteSafeUnserialise();

		foreach ($tests as $test) {
			extract($test);
			$desc = json_encode($test);
			$caught = false;
			try {
				$serial->expect($data, $offset, $expected);
				$this->assertSame($result, $offset, $desc);
			} catch (Exception $e) {
				$caught = true;
			}
			if ($result === null) {
				$this->assertTrue($caught);
			}
		}
	}

	public function testLength() {
		$serial = new LyteSafeUnserialise();
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
			$caught = false;
			try {
				$serial->getLength($data, $offset);
			} catch (Exception $e) {
				$caught = true;
			}
			$this->assertTrue($caught);
		};

		$checkInvalidLength('1', 0);
		$checkInvalidLength(':1', 1);
		$checkInvalidLength('1234123', 1);
	}

	public function testGetType() {
		$tests = array(
			array(
				'data' => 'a:',
				'expectedOffset' => 1,
				'expectedType' => 'array',
			),
			array(
				'data' => 'N;',
				'expectedOffset' => 1,
				'expectedType' => 'null',
			),
			array(
				'data' => 's:',
				'expectedOffset' => 1,
				'expectedType' => 'string',
			),
			array(
				'data' => 'O:',
				'throws' => true,
			),
		);
		$serial = new LyteSafeUnserialise();

		foreach ($tests as $test) {
			$throws = false;
			extract($test);
			$offset = 0;
			$caught = false;
			try {
				$type = $serial->getType($data, $offset);
				$this->assertSame($expectedType, $type);
				$this->assertSame($expectedOffset, $offset);
			} catch (Exception $e) {
				$caught = true;
			}
			$this->assertSame($throws, $caught);
		}
	}
}