<?php
namespace Lyte\Serial;
class Unserializer {
	private $data;
	private $length;

	/**
	 * Our offset through the data
	 *
	 * This is public, but you're not actually expected to update it. I'm not
	 * your mum though.
	 *
	 * @var int
	 */
	public $offset = 0;

	public function __construct($data) {
		if (!is_string($data)) {
			throw new Exception("Data supplied for unserialization was not a string");
		}
		$this->data = $data;
		$this->length = strlen($data);
	}

	/**
	 * Unserialize the entire string
	 */
	public function unserialize() {
		$this->offset = 0;
		$ret = $this->unserializeComponent();
		if ($this->offset !== $this->length) {
			throw new Exception("Data continues beyond end of initial value");
		}
		return $ret;
	}

	/**
	 * Unserialize the component at the current offset
	 */
	private function unserializeComponent() {
		$method = 'unserialize'.$this->getType();
		$ret = $this->$method();
		return $ret;
	}

	public function getType() {
		static $types = array(
			'a' => 'array',
			'N' => 'null',
			's' => 'string',
			'i' => 'integer',
			'b' => 'boolean',
			'd' => 'double',
		);
		if (!isset($this->data[$this->offset])) {
			throw new Exception("Insufficient data to get type at offset {$this->offset}");
		}
		$type = $this->data[$this->offset];
		if (!isset($types[$type])) {
			throw new Exception("Unhandled type '{$type}'");
		}
		$this->offset++;
		return $types[$type];
	}

	public function expect($expected) {
		$length = strlen($expected);
		for ($i = 0; $i < $length; $i++) {
			if ($i >= $this->length) {
				throw new Exception("Ran out of data");
			}
			if ($expected[$i] !== $this->data[$this->offset]) {
				throw new Exception("Unexpected character at {$this->offset}, got '{$this->data[$this->offset]}' expecting '{$expected[$i]}'");
			}
			$this->offset++;
		}
	}

	public function unserializeNull() {
		$this->expect(';');
		return null;
	}

	public function unserializeString() {
		$this->expect(':');
		$length = $this->getLength($this->data, $this->offset);
		$this->expect(':"');
		$str = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		$this->expect('";');
		return $str;
	}

	public function regex($pattern) {
		if (!preg_match($pattern, substr($this->data, $this->offset), $match)) {
			throw new Exception("Unable to detect pattern at {$this->offset}");
		}
		$this->offset += strlen($match[0]);
		return $match;
	}

	public function unserializeInteger() {
		$this->expect(':');
		$match = $this->regex('%^(-?[0-9]+);%S');
		return (int)$match[1];
	}

	public function unserializeDouble() {
		$this->expect(':');
		static $pattern = '%^
			(
				-? # optionally negative
				[0-9]+ # number must exist
				(\.[0-9]+)? # optional decimal place
				(E\+[0-9]+)? # optional exponent
			);
		%xS';
		$match = $this->regex($pattern);
		return (double)$match[1];
	}

	public function unserializeBoolean() {
		static $map = array(
			'1' => true,
			'0' => false,
		);
		$this->expect(':');
		$val = $this->data[$this->offset];
		if (!isset($map[$val])) {
			throw new Exception("$val is not an acceptable boolean");
		}
		$this->offset++;
		$this->expect(';');
		return $map[$val];
	}

	/**
	 * Unserialise an array starting at $offset
	 */
	public function unserializeArray() {
		$this->expect(':');
		$length = $this->getLength();
		$this->expect(':{');
		$arr = array();

		for ($i = 0; $i < $length; $i++) {
			$key = $this->unserializeComponent();
			$val = $this->unserializeComponent();
			$arr[$key] = $val;
		}

		$this->expect('}');

		return $arr;
	}

	/**
	 * Get the length of whatever is starting at $offset
	 */
	public function getLength() {
		$length = 0;
		do {
			$length .= $this->data[$this->offset];
			$this->offset++;
			if (!isset($this->data[$this->offset])) {
				throw new Exception('Unable to determine length');
			}
		} while ($this->data[$this->offset] >= '0' && $this->data[$this->offset] <= '9');
		return (int)$length;
	}
}
