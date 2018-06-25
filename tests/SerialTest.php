<?php
namespace Lyte\Serial\Tests;
use Lyte\Serial\Serial;
class SerialTest extends TestCase {
	public function testStaticUnserialize() {
		$this->assertSame("foo", Serial::unserialize('s:3:"foo";'));
		$this->assertSame(false, Serial::unserialize("b:0;"));
	}

	public function testInstanceUnserialize() {
		$serial = new Serial;
		$this->assertSame("foo", $serial->unserialize('s:3:"foo";'));
		$this->assertSame(false, $serial->unserialize("b:0;"));
	}

	public function testThrows() {
		$caught = false;
		$serial = new Serial;
		try {
			$serial->unserialize(false);
		} catch (\Exception $e) {
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testIsSerialized() {
		$serial = new Serial;
		$this->assertSame(true, $serial->isSerialized('s:3:"foo";'));
		$this->assertSame(true, Serial::isSerialized('s:3:"foo";'));
		$this->assertSame(false, $serial->isSerialized('s:3:"foob";'));
		$this->assertSame(false, Serial::isSerialized(false));
		$this->assertSame(true, Serial::isSerialized(serialize(false)));
		$this->assertSame(true, Serial::isSerialized(serialize(null)));
		$this->assertSame(false, $serial->isSerialized(''));
		$this->assertSame(false, Serial::isSerialized([]));
	}
}
