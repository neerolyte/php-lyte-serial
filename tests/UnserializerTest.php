<?php
namespace Lyte\Serial\Tests;
use Lyte\Serial\Unserializer;

class UnserializerTest extends TestCase {
	public static function getDataForUnserializes() {
		return [
			[null],
			['foo'],
			['bar'],
			['foobarbaz'],
			['foobarbazfoobarbazfoobarbaz'],
			["foo\x00bar"],
			['😄👏🎆'],
			['你好世界'],
			["\x93bendy quotes\x94"],
			[[]],
			[[1]],
			[['foo']],
			[[chr(0) => chr(0)]],
			[true],
			[false],
			[1],
			[-42],
			[1.23],
			[.987129837123],
			[-812673987612398761298736129367],
			[['foo' => ['bar']]],
			[['foo' => [serialize('bar')]]],
		];
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

	public static function getDataForUnserializeFails() {
		return [
			['b:1', 'missing trailing ";" on boolean'],
			['b:3;', 'bools are only 1 or 0'],
			["i:12-12;", "can't have minus sign in the middle of integer"],
			[serialize(new \stdClass()), 'No classes allowed'],
			[serialize([new \stdClass()]), 'No classes allowed even in arrays'],
			['C:8:"stdClass":0:{}', 'No constructors allowed either'],
			['a:3:{}', 'array with incorrect length'],
			["N;\x00", 'trailing bytes'],
		];
	}

	/**
	 * @dataProvider getDataForUnserializeFails
	 */
	public function testUnserializeFails($string, $desc) {
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

	public static function getDataForExpectPassing() {
		return [
			['1234', '123', 0, 3],
			['123', '3', 2, 3],
			['123', '12', 0, 2],
		];
	}

	/**
	 * @dataProvider getDataForExpectPassing
	 */
	public function testExpectPassing($data, $chars, $offset, $expectedOffset) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$serial->expect($chars);
		$this->assertSame($expectedOffset, $serial->offset);
	}

	public static function getDataForExpectFailing() {
		return [
			['12', '2'],
			['12', '123'],
		];
	}

	/**
	 * @dataProvider getDataForExpectFailing
	 */
	public function testExpectFailing($data, $chars) {
		$serial = new Unserializer($data);
		$caught = false;
		try {
			$serial->expect($chars);
		} catch (\Exception $e) {
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public static function getDataForGetLength() {
		return [
			['1:', 0, 1, 1],
			['12:', 0, 12, 2],
			['12345:', 1, 2345, 5],
		];
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

	public static function getDataForInvalidLengths() {
		return [
			['1', 0],
			[':1', 1],
			['1234123', 1],
		];
	}

	/**
	 * @dataProvider getDataForInvalidLengths
	 */
	public function testInvalidLength($data, $offset) {
		$serial = new Unserializer($data);
		$serial->offset = $offset;
		$caught = false;
		try {
			$serial->getLength();
		} catch (\Exception $e) {
			$caught = ($e->getMessage() === 'Unable to determine length');
		}
		$this->assertTrue($caught, 'Unable to determine length for data at offset ' . $offset);
	}

	public static function getDataForGetTypes() {
		return [
			['a', 'array', 0, 1],
			['Xa', 'array', 1, 2],
			['N', 'null', 0, 1],
			['s', 'string', 0, 1],
			['b', 'boolean', 0, 1],
			['i', 'integer', 0, 1],
			['d', 'double', 0, 1],
		];
	}

	/**
	 * @dataProvider getDataForGetTypes
	 */
	public function testGetType($data, $expectedType, $offset, $expectedOffset) {
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

	public function testLengthMismatchThrowsWithoutRepair() {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$bad = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$this->setExpectedException('Exception', 'does not align with closing quote');
		(new Unserializer($bad))->unserialize();
	}

	public static function getDataForRepairLengthsDisabled() {
		return [
			[false],
			[0],
			['0'],
			['false'],
			['no'],
			['off'],
			[''],
			[null],
		];
	}

	/**
	 * @dataProvider getDataForRepairLengthsDisabled
	 */
	public function testRepairLengthsOptionDisabled($val) {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$badInner = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$blob = 'a:1:{s:3:"top";s:'.strlen($badInner).':"'.$badInner.'";}';
		$u = new Unserializer($blob, ['repairLengths' => $val]);
		$u->unserialize();
		$this->assertSame([], $u->lengthIssues, 'repairLengths '.var_export($val, true));
	}

	public static function getDataForRepairLengthsEnabled() {
		return [
			[true],
			[1],
			['1'],
			['true'],
			['yes'],
			['on'],
		];
	}

	/**
	 * @dataProvider getDataForRepairLengthsEnabled
	 */
	public function testRepairLengthsOptionEnabled($val) {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$badInner = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$blob = 'a:1:{s:3:"top";s:'.strlen($badInner).':"'.$badInner.'";}';
		$u = new Unserializer($blob, ['repairLengths' => $val]);
		$u->unserialize();
		$this->assertNotSame([], $u->lengthIssues, 'repairLengths '.var_export($val, true));
	}

	public function testRepairDetectsNestedWrongStringLength() {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$this->assertSame(20, strlen($ext));
		$badInner = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$blob = 'a:1:{s:3:"top";s:'.strlen($badInner).':"'.$badInner.'";}';

		$u = new Unserializer($blob, ['repairLengths' => true]);
		$u->unserialize();
		$this->assertNotSame([], $u->lengthIssues);
		$issue = $u->lengthIssues[0];
		$this->assertSame(24, $issue['declared']);
		$this->assertSame(20, $issue['actual']);
		$this->assertSame(['top', 'rules'], $issue['path']);
	}

	public function testRepairSerializedStringRewiresLength() {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$badInner = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$blob = 'a:1:{s:3:"top";s:'.strlen($badInner).':"'.$badInner.'";}';

		list($fixed) = Unserializer::repairSerializedString($blob);
		$this->assertTrue(strpos($fixed, 's:20:"') !== false);
		$this->assertTrue(strpos($fixed, 's:24:"') === false);
		$check = new Unserializer($fixed, ['repairLengths' => true]);
		$check->unserialize();
		$this->assertSame([], $check->lengthIssues);
	}

	public function testRepairSerializedStringMatchesCanonicalUnserialize() {
		$ext = ".pdf\n.docx\n.doc\n.jpg";
		$badInner = 'a:1:{s:5:"rules";s:24:"'.$ext.'";}';
		$blob = 'a:1:{s:3:"top";s:'.strlen($badInner).':"'.$badInner.'";}';
		$goodInner = 'a:1:{s:5:"rules";s:20:"'.$ext.'";}';
		$expectedBlob = 'a:1:{s:3:"top";s:'.strlen($goodInner).':"'.$goodInner.'";}';

		list($fixed) = Unserializer::repairSerializedString($blob);
		$this->assertSame($expectedBlob, $fixed);
	}
}
