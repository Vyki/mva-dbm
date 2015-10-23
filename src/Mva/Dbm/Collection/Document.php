<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Collection;

class Document
{

	/**
	 * @param  array $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		return (array) $this;
	}

	/**
	 * @param  string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		throw new MemberAccessException("Item '$name' does not exist");
	}

}
