<?php

/**
 * A really space efficient Bloom filter implementation.
 * The bit field is stored in a string and operations are done bitwise.
 * This has a slight performance penalty when compared to other array-based
 * implementations, but saves a lot of space.
 * 
 * Initialization (adding values to the set) takes roughly twice as long as array
 * based BloomFilter implementations, but lookup times are virtually identical.
 * Memory usage differs by several orders of magnitude, peak memory usage differs
 * by about one order of magnitude. The serialized size differs by several
 * orders of magnitude.
 * 
 * When serializing an instance using serialize(), the resulting string *will*
 * contain non-printable characters! The serialized representation must be handled
 * using binary safe functions.
 * 
 * An alternative 7-bit ASCII-safe representation can be obtained by __toString/casting
 * the object to a string. This representation can be unserialized using
 * BloomFilter::unserializeFromStringRepresentation(). This ASCII-safe representation
 * takes about eight times more space than the straight serialized version.
 */
class BloomFilter {

	protected $bitField = '';
	protected $m;
	protected $k;
	
	/**
	 * @param int $m Size of the bit field
	 * @param int $k Number of hash functions
	 */	
	public function __construct($m, $k) {
		if (!is_numeric($m) || !is_numeric($k)) {
			throw new InvalidArgumentException('$m and $k must be integers');
		}
		$this->bitField = $this->initializeBitFieldOfLength($m);
		$this->m = (int)$m;
		$this->k = (int)$k;
	}
	
	/**
	 * Calculates the optimal number of k given m and a
	 * typical number of items to be stored.
	 * 
	 * @param int $m Size of the bit field
	 * @param int $n Typical number of items to insert
	 * @return int Optimal number for k
	 */
	public static function getK($m, $n) {
		return ceil(($m / $n) * log(2));
	}
	
	public static function constructForTypicalSize($m, $n) {
		return new self($m, self::getK($m, $n));
	}
	
	/**
	 * Unserializes in instance from an ASCII safe string representation produced by __toString.
	 * 
	 * @param string $string String representation
	 * @return BloomFilter Unserialized instance
	 */
	public static function unserializeFromStringRepresentation($string) {
		if (!preg_match('~k:(?P<k>\d+)/m:(?P<m>\d+)\((?P<bitfield>[01]+)\)~', $string, $matches)) {
			throw new InvalidArgumentException('Invalid strings representation');
		}
		$bf = new self((int)$matches['m'], (int)$matches['k']);
		$bf->bitField = pack('H*', join(array_map(function ($byte) { return str_pad(base_convert($byte, 2, 16), 2, '0', STR_PAD_LEFT); }, str_split($matches['bitfield'], 8))));
		return $bf;
	}

	protected function initializeBitFieldOfLength($length) {
		return str_repeat("\x0", ceil($length / 8));
	}
		
	protected function setBitAtPosition($pos) {
		list($char, $byte) = $this->position2CharAndByte($pos);
		$this->bitField[$char] = $this->bitField[$char] | $byte;
	}
	
	protected function getBitAtPosition($pos) {
		list($char, $byte) = $this->position2CharAndByte($pos);
		return ($this->bitField[$char] & $byte) === $byte;
	}
	
	/**
	 * Returns a tuple with the char offset into the bitfield string
	 * in index 0 and a bitmask for the specific position in index 1.
	 * E.g.: Position 9 -> (1, "10000000") (2nd byte, "first" bit)
	 * 
	 * @param int $pos The $pos'th bit in the bit field.
	 * @return array array(int $charOffset, string $bitmask)
	 */
	protected function position2CharAndByte($pos) {
		if ($pos > $this->m) {
			throw new InvalidArgumentException("\$pos of $pos beyond bitfield length of $this->m");
		}

		static $positionMap = array(
			8 => "\x1",
			7 => "\x2",
			6 => "\x4",
			5 => "\x8",
			4 => "\x10",
			3 => "\x20",
			2 => "\x40",
			1 => "\x80"
		);
		
		$char = ceil($pos / 8) - 1;
		$byte = $positionMap[$pos % 8 ?: 8];
		return array($char, $byte);
	}
	
	/**
	 * Calculates the positions a value hashes to in the bitfield.
	 * 
	 * @param string $value The value to insert into the bitfield.
	 * @return SqlFixedArray Array containing the numeric positions in the bitfield.
	 */
	protected function positions($value) {
		mt_srand(crc32($value));

		$positions = new SplFixedArray($this->k);
		for ($i = 0; $i < $this->k; $i++) {
			$positions[$i] = mt_rand(1, $this->m);
		}

		return $positions;
	}
	
	/**
	 * Add a value into the set.
	 * 
	 * @param string $value
	 */
	public function add($value) {
		foreach ($this->positions($value) as $position) {
			$this->setBitAtPosition($position);
		}
	}
	
	/**
	 * Checks if the value may have been added to the set before.
	 * False positives are possible, false negatives are not.
	 * 
	 * @param string $value
	 * @return boolean
	 */
	public function maybeInSet($value) {
		foreach ($this->positions($value) as $position) {
			if (!$this->getBitAtPosition($position)) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Returns an ASCII representation of the current bit field.
	 * 
	 * @return string
	 */
	public function showBitField() {
		return join(array_map(function ($chr) { return str_pad(base_convert(bin2hex($chr), 16, 2), 8, '0', STR_PAD_LEFT); }, str_split($this->bitField)));
	}
	
	/**
	 * Returns an ASCII safe representation of the BloomFilter object.
	 * This representation can be unserialized using unserializeFromStringRepresentation().
	 * 
	 * @return string
	 */
	public function __toString() {
		return "k:$this->k/m:$this->m(" . $this->showBitField() . ')';
	}
	
}
