<?php
// even if we upgrade to php7, don't use native serialize
// see: https://media.ccc.de/v/33c3-7858-exploiting_php7_unserialize#video&t=325
class LyteSafeUnserialise {
	public function unserialize($data) {
		if (!is_string($data)) {
			throw new Exception("Data supplied for unserialisation was not a string.");
		}
		$offset = 0;
		$ret = $this->_unserialize($data, $offset);
		if ($offset !== strlen($data)) {
			throw new Exception("Data continues beyond end of initial value");
		}
		return $ret;
	}

	public function _unserialize($data, &$offset) {
		$method = 'unserialize'.$this->getType($data, $offset);
		return $this->$method($data, $offset);
	}

	public function getType($data, &$offset) {
		static $types = array(
			'a' => 'array',
			'N' => 'null',
			's' => 'string',
			'i' => 'integer',
			'b' => 'boolean',
			'd' => 'double',
		);
		$type = $data[$offset];
		if (!isset($types[$type])) {
			throw new Exception("Unhandled type '{$type}'");
		}
		$offset++;
		return $types[$type];
	}

	public function expect($data, &$offset, $expected) {
		for ($i = 0; $i < strlen($expected); $i++) {
			if ($expected[$i] !== $data[$offset]) {
				throw new Exception("Unexpected character at $offset, got '{$data[$offset]}' expecting '{$expected[$i]}'");
			}
			$offset++;
		}
	}

	public function unserializeNull($data, &$offset) {
		$this->expect($data, $offset, ';');
		return null;
	}

	public function unserializeString($data, &$offset) {
		$this->expect($data, $offset, ':');
		$length = $this->getLength($data, $offset);
		$this->expect($data, $offset, ':"');
		$str = substr($data,$offset, $length);
		$offset += $length;
		$this->expect($data, $offset, '";');
		return $str;
	}

	public function unserializeInteger($data, &$offset) {
		$this->expect($data, $offset, ':');
		if (!preg_match('%^(-?[0-9]+);%', substr($data, $offset), $match)) {
			throw new Exception("Unable to find integer at $offset");
		}
		$res = (int)$match[1];
		$offset += strlen($match[0]);
		return $res;
	}

	public function unserializeDouble($data, &$offset) {
		$this->expect($data, $offset, ':');
		static $re = '%^
			(
				-? # optionally negative
				[0-9]+ # number must exist
				(\.[0-9]+)? # optional decimal place
				(E\+[0-9]+)? # optional exponent
			);
		%xS';
		if (!preg_match($re, substr($data, $offset), $match)) {
			throw new Exception("Unable to find double at $offset");
		}
		$res = (double)$match[1];
		$offset += strlen($match[0]);
		return $res;
	}

	public function unserializeBoolean($data, &$offset) {
		static $map = array(
			'1' => true,
			'0' => false,
		);
		$this->expect($data, $offset, ':');
		$val = $data[$offset];
		if (!isset($map[$val])) {
			throw new Exception("$val is not an acceptable boolean");
		}
		$offset++;
		$this->expect($data, $offset, ';');
		return $map[$val];
	}

	/**
	 * Unserialise an array starting at $offset
	 */
	public function unserializeArray($data, &$offset) {
		$this->expect($data, $offset, ':');
		$length = $this->getLength($data, $offset);
		$this->expect($data, $offset, ':{');
		$arr = array();

		for ($i = 0; $i < $length; $i++) {
			$key = $this->_unserialize($data, $offset);
			$val = $this->_unserialize($data, $offset);
			$arr[$key] = $val;
		}

		$this->expect($data, $offset, '}');

		return $arr;
	}

	/**
	 * Get the length of whatever is starting at $offset
	 */
	public function getLength($data, &$offset) {
		$length = 0;
		do {
			$length .= $data[$offset];
			$offset++;
			if (!isset($data[$offset])) {
				throw new Exception('Unable to determine length');
			}
		} while ($data[$offset] >= '0' && $data[$offset] <= '9');
		return (int)$length;
	}
}
