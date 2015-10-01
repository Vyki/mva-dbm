<?php
namespace Mva\Dbm;

/**
 * Single document representation.
 * @author Roman Vykuka
 * 
 */
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
		throw new MemberAccessException("Column '$name' does not exist");
	}
}
