<?php
namespace Lyte\Serial;
class Unserializer {
	private $data;
	private $length;

	/**
	 * When true, string lengths that do not line up with the closing quote are
	 * inferred (nested serialized payload first, then a conservative quote scan
	 * for plain strings with no embedded double quotes). Nested serialized
	 * payloads inside string values are also scanned for length mismatches.
	 *
	 * @var bool
	 */
	private $repairLengths = false;

	/**
	 * Recursion depth for scanning nested serialized strings (0 = top level).
	 *
	 * @var int
	 */
	private $depth = 0;

	/**
	 * @var int
	 */
	private $maxDepth = 64;

	/**
	 * Each entry: declared, actual, lengthDigitStart, lengthDigitEnd (end exclusive),
	 * path (list of keys), optional reason.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $lengthIssues = [];

	/**
	 * Stack of path segments while parsing (int keys and string keys).
	 *
	 * @var array<int, int|string>
	 */
	private $pathStack = [];

	/**
	 * Our offset through the data
	 *
	 * This is public, but you're not actually expected to update it. I'm not
	 * your mum though.
	 *
	 * @var int
	 */
	public $offset = 0;

	/**
	 * @param string $data
	 * @param array{repairLengths?: mixed, depth?: int, maxDepth?: int} $options
	 *        repairLengths: coerced with filter_var(..., FILTER_VALIDATE_BOOLEAN,
	 *        FILTER_NULL_ON_FAILURE); only strict true enables repair (e.g. true,
	 *        1, "1", "true", "on", "yes"). false, 0, "0", "false", "off", "no", "",
	 *        null, and invalid values leave repair off.
	 */
	public function __construct($data, array $options = []) {
		if (!is_string($data)) {
			throw new Exception("Data supplied for unserialization was not a string");
		}
		$this->data = $data;
		$this->length = strlen($data);
		if (array_key_exists('repairLengths', $options)) {
			$this->repairLengths = self::parseRepairLengthsOption($options['repairLengths']);
		}
		if (isset($options['depth'])) {
			$this->depth = (int) $options['depth'];
		}
		if (isset($options['maxDepth'])) {
			$this->maxDepth = (int) $options['maxDepth'];
		}
	}

	/**
	 * @param mixed $value
	 */
	private static function parseRepairLengthsOption($value) {
		return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
	}

	/**
	 * Unserialize the entire string
	 */
	public function unserialize() {
		$this->offset = 0;
		$this->pathStack = [];
		$ret = $this->unserializeComponent();
		if ($this->offset !== $this->length) {
			throw new Exception("Data continues beyond end of initial value");
		}
		return $ret;
	}

	/**
	 * Parse a single serialized value from the start of $blob (offset 0).
	 * Does not require the blob to end after the value.
	 *
	 * @param string $blob
	 * @param array{repairLengths?: mixed, depth?: int, maxDepth?: int} $options
	 * @return array{0: mixed, 1: int} [value, bytesConsumed]
	 */
	public static function parseOne($blob, array $options = []) {
		$u = new self($blob, $options);
		$u->pathStack = [];
		$u->offset = 0;
		$val = $u->unserializeComponent();
		return [$val, $u->offset];
	}

	/**
	 * Repair declared string lengths in $data by parsing with inference and
	 * rewriting length digits. May require multiple passes when nested strings
	 * and outer wrappers are both wrong. Returns the repaired wire string and
	 * the collected lengthIssues from the final successful parse.
	 *
	 * @param string $data
	 * @param int $maxPasses
	 * @return array{0: string, 1: array<int, array<string, mixed>>}
	 */
	public static function repairSerializedString($data, $maxPasses = 16) {
		$s = $data;
		$issues = [];
		for ($p = 0; $p < $maxPasses; $p++) {
			$u = new self($s, ['repairLengths' => true]);
			$u->unserialize();
			$issues = $u->lengthIssues;
			if ($issues === []) {
				return [$s, $issues];
			}
			usort($issues, function ($a, $b) {
				return $b['lengthDigitStart'] - $a['lengthDigitStart'];
			});
			foreach ($issues as $issue) {
				$ds = $issue['lengthDigitStart'];
				$de = $issue['lengthDigitEnd'];
				$old = (string) $issue['declared'];
				$new = (string) $issue['actual'];
				if ($old === $new) {
					continue;
				}
				$s = substr($s, 0, $ds) . $new . substr($s, $de);
			}
		}
		throw new Exception('repairSerializedString: could not stabilise after ' . $maxPasses . ' passes');
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
		static $types = [
			'a' => 'array',
			'N' => 'null',
			's' => 'string',
			'i' => 'integer',
			'b' => 'boolean',
			'd' => 'double',
		];
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
		$lengthDigitStart = $this->offset;
		$declaredLength = $this->getLength();
		$lengthDigitEnd = $this->offset;
		$this->expect(':"');
		$contentStart = $this->offset;

		$validClose = (
			$contentStart + $declaredLength <= $this->length
			&& isset($this->data[$contentStart + $declaredLength])
			&& $this->data[$contentStart + $declaredLength] === '"'
		);

		$actualLength = $declaredLength;
		if (!$validClose) {
			if (!$this->repairLengths) {
				throw new Exception(
					"String length mismatch at offset {$contentStart}: declared {$declaredLength} bytes does not align with closing quote"
				);
			}
			$inferred = $this->inferStringByteLength($contentStart, $declaredLength);
			if ($inferred === null) {
				throw new Exception(
					"String length mismatch at offset {$contentStart}: declared {$declaredLength} bytes and could not infer actual length"
				);
			}
			$actualLength = $inferred;
			if ($actualLength !== $declaredLength) {
				$this->lengthIssues[] = [
					'declared' => $declaredLength,
					'actual' => $actualLength,
					'lengthDigitStart' => $lengthDigitStart,
					'lengthDigitEnd' => $lengthDigitEnd,
					'path' => $this->pathStack,
				];
			}
		}

		$str = substr($this->data, $contentStart, $actualLength);
		$this->offset = $contentStart + $actualLength;
		$this->expect('";');

		if ($this->repairLengths && $this->depth < $this->maxDepth && $str !== '') {
			$this->collectLengthIssuesFromNestedStringPayload($contentStart, $str);
		}

		return $str;
	}

	/**
	 * Parse nested serialized values inside $str and merge length issues into this
	 * parser, shifting digit offsets by $contentStart (absolute in $this->data).
	 *
	 * @param int $contentStart
	 * @param string $str
	 */
	private function collectLengthIssuesFromNestedStringPayload($contentStart, $str) {
		if (!$this->looksLikeSerializedPayloadOnString($str)) {
			return;
		}
		try {
			$inner = new self($str, [
				'repairLengths' => true,
				'depth' => $this->depth + 1,
				'maxDepth' => $this->maxDepth,
			]);
			$inner->pathStack = $this->pathStack;
			$inner->offset = 0;
			$inner->unserializeComponent();
			if ($inner->offset !== strlen($str)) {
				return;
			}
			foreach ($inner->lengthIssues as $issue) {
				$issue['lengthDigitStart'] += $contentStart;
				$issue['lengthDigitEnd'] += $contentStart;
				$this->lengthIssues[] = $issue;
			}
		} catch (\Exception $e) {
			return;
		}
	}

	/**
	 * @param string $s
	 * @return bool
	 */
	private function looksLikeSerializedPayloadOnString($s) {
		if ($s === '') {
			return false;
		}
		$c = $s[0];
		return $c === 'a' || $c === 'N' || $c === 'i' || $c === 'b' || $c === 'd' || $c === 's';
	}

	/**
	 * @param int $contentStart
	 * @param int $declaredLength
	 * @return int|null
	 */
	private function inferStringByteLength($contentStart, $declaredLength) {
		if ($this->looksLikeSerializedPayload($contentStart)) {
			$nested = substr($this->data, $contentStart);
			try {
				list(, $consumed) = self::parseOne($nested, [
					'repairLengths' => $this->repairLengths,
					'depth' => $this->depth,
					'maxDepth' => $this->maxDepth,
				]);
			} catch (\Exception $e) {
				$consumed = null;
			}
			if ($consumed !== null
				&& $contentStart + $consumed <= $this->length
				&& isset($this->data[$contentStart + $consumed])
				&& $this->data[$contentStart + $consumed] === '"'
			) {
				return $consumed;
			}
			return null;
		}
		return $this->inferPlainStringLengthByQuoteScan($contentStart);
	}

	/**
	 * @param int $contentStart
	 * @return bool
	 */
	private function looksLikeSerializedPayload($contentStart) {
		if ($contentStart >= $this->length) {
			return false;
		}
		$c = $this->data[$contentStart];
		return $c === 'a' || $c === 'N' || $c === 'i' || $c === 'b' || $c === 'd' || $c === 's';
	}

	/**
	 * First closing `";` after content start with no raw `"` inside the payload.
	 * Unsafe for arbitrary binary; only used when the payload does not look like
	 * nested serialized data.
	 *
	 * @param int $contentStart
	 * @return int|null
	 */
	private function inferPlainStringLengthByQuoteScan($contentStart) {
		$pos = strpos($this->data, '";', $contentStart);
		if ($pos === false) {
			return null;
		}
		$L = $pos - $contentStart;
		if ($L < 0) {
			return null;
		}
		$chunk = substr($this->data, $contentStart, $L);
		if (strpos($chunk, '"') !== false) {
			return null;
		}
		return $L;
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
		return (float) $match[1];
	}

	public function unserializeBoolean() {
		static $map = [
			'1' => true,
			'0' => false,
		];
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
		$arr = [];

		for ($i = 0; $i < $length; $i++) {
			$key = $this->unserializeComponent();
			$this->pathStack[] = $key;
			$val = $this->unserializeComponent();
			array_pop($this->pathStack);
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
