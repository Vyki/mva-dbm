<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Collection\Document;

use Mva\Dbm\MemberAccessException;

class Document implements \ArrayAccess, \IteratorAggregate, \Countable
{

	public function __construct($data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}

	public function toArray()
	{
		return (array) $this;
	}

	public function __get($name)
	{
		throw new MemberAccessException("Item '$name' does not exist");
	}

	public function count()
	{
		return count((array) $this);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this);
	}

	public function offsetSet($key, $val)
	{
		$this->$key = $val;
	}

	public function offsetGet($key)
	{
		return $this->$key;
	}

	public function offsetExists($key)
	{
		return isset($this->$key);
	}

	public function offsetUnset($key)
	{
		unset($this->$key);
	}

}
