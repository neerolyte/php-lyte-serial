<?php
// even if we upgrade to php7, don't use native serialize
// see: https://media.ccc.de/v/33c3-7858-exploiting_php7_unserialize#video&t=325
class LyteSafeUnserialise {
	public function unserialize($data) {
		if (!is_string($data)) {
			throw new Exception("Data supplied for unserialisation was not a string.");
		}
		$offset = 0;
		return $this->unserializeType($data, $offset);
	}

	public function unserializeType($data, &$offset) {
		$method = 'unserialize'.$this->getType($data, $offset);
		return $this->$method($data, $offset);
	}

	public function getType($data, &$offset) {
		static $types = array(
			'a' => 'array',
			'N' => 'null',
			's' => 'string',
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
				throw new Exception("Unpexcted character at $offset, got '{$data[$offset]}' expecting '{$expected[$i]}'.");
			}
			$offset++;
		}
	}

	public function unserializeNull() {
		return null;
	}

	public function unserializeString($data, &$offset) {
		$this->expect($data, $offset, ':');
		$length = $this->getLength($data, $offset);
		$this->expect($data, $offset, ':"');
		$str = substr($data,$offset, $length);
		$offset += $length + 1;
		return $str;
	}

	/**
	 * Unserialise an array starting at $offset
	 */
	public function unserializeArray($data, $offset) {
		$offset += 2;
		$length = $this->getLength($data, $offset);
		$arr = array();

		for ($i = 0; $i < $length; $i++) {
			fwrite(STDERR, $data."\n");
			$arr []= $unserialize();
		}

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
		} while ($data[$offset] >= '0' && $data[$offset] <= '9');
		return (int)$length;
	}
}
