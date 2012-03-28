Space efficient binary Bloom filter
===================================

A really space efficient Bloom filter implementation. The bit field is stored in a string and operations are done bitwise. This has a slight performance penalty when compared to other array-based implementations, but saves a lot of space.

Initialization (adding values to the set) takes roughly twice as long as array based BloomFilter implementations, but lookup times are virtually identical. Memory usage differs by several orders of magnitude, peak memory usage differs by about one order of magnitude. The serialized size differs by several orders of magnitude.

When serializing an instance using `serialize()`, the resulting string *will* contain non-printable characters! The serialized representation must be handled using binary safe functions. The serialized size is roughly `$m/8` bytes.

An alternative 7-bit ASCII-safe representation can be obtained by casting
the object to a string and unserialized using `BloomFilter::unserializeFromStringRepresentation`. This ASCII-safe representation
takes about 33% more space than the straight serialized version.

Usage
-----

	$bf = new BloomFilter(40000, 14);
	$bf->add('foo');
	$bf->add('bar');
	$bf->add('baz');
	
	if ($bf->maybeInSet('foo')) …
	
Instead of guesstimating the second constructor argument (`k`, number of hash functions) manually, use `BloomFilter::constructForTypicalSize` by specifying a bit field size and the expected number of values:

    // a 5 KiB instance for ~2000 values
    $bf = BloomFilter::constructForTypicalSize(40000, 2000);
    
The main advantage of this BloomFilter class is that it can be efficiently serialized, for example to be used for pre-computed sets. Two options for serialization:

    $serialized = serialize($bf);
    $bf = unserialize($serialized);
    
Using the standard PHP serialization facilities will result in a non-ASCII-safe binary representation which can quickly be dumped to and read from a file, for example.

If a 7-bit ASCII-safe representation is necessary, cast the object to a string and unserialize it using `BloomFilter::unserializeFromStringRepresentation`:

    $serialized = (string)$bf;
    $bf = BloomFilter::unserializeFromStringRepresentation($serialized);
    
Requirements
------------

- PHP 5.3+

No special extensions necessary.

Licence
-------

Provided as is without any guarantees, do with it whatever you like.
