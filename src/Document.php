<?php

namespace Mva\Mongo;

/**
 * Single document representation.
 * Document is based on the library Nette\Database http://doc.nette.org/en/2.3/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @author Roman Vykuka
 * 
 */
class Document implements \IteratorAggregate, \ArrayAccess
{

	/** @var Collection */
	protected $collection;

	/** @var array of row data */
	protected $data;

	/** @var bool */
	protected $dataRefreshed = FALSE;

	public function __construct(array $data, Collection $table)
	{
		$this->data = $data;
		$this->collection = $table;
	}

	public function getPrimary()
	{
		$key = $this->collection->getPrimary();

		if (isset($this->data[$key])) {
			return $this->data[$key];
		}

		throw new InvalidStateException("Document does not contain primary key $key data.");
	}

	/**
	 * @internal
	 * @ignore
	 */
	public function setCollection(Collection $table)
	{
		$this->collection = $table;
	}

	/**
	 * @internal
	 */
	public function getCollection()
	{
		return $this->collection;
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		return $this->data;
	}

	/**
	 * Updates row.
	 * @param  array (column => value)
	 * @return bool
	 */
	public function update($data)
	{
		$collection = $this->collection->createCollectionInstance()->wherePrimary($this->getPrimary());

		if ($collection->update($data)) {

			if (($row = $collection->fetch()) === FALSE) {
				throw new InvalidStateException('Database refetch failed; item does not exist!');
			}

			$this->data = $row->data;

			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Deletes row.
	 * @return int number of affected rows
	 */
	public function delete()
	{
		$id = $this->getPrimary();

		$res = $this->collection->createCollectionInstance()
				->wherePrimary($id)
				->delete();

		if ($res) {
			unset($this->collection[$id]);
		}

		return $res;
	}

	################## interface IteratorAggregate ##################

	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}

	################## interface ArrayAccess & magic accessors ################## 

	/**
	 * Stores value in item.
	 * @param  string item key
	 * @param  string value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->__set($key, $value);
	}

	/**
	 * Returns value of item.
	 * @param  string item key
	 * @return string
	 */
	public function offsetGet($key)
	{
		return $this->__get($key);
	}

	/**
	 * Tests if item exists.
	 * @param  string column name
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->__isset($key);
	}

	/**
	 * Removes item from data.
	 * @param  string column name
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->__unset($key);
	}

	public function __set($key, $value)
	{
		throw new NotSupportedException('Document is read-only; use update() method instead.');
	}

	public function &__get($key)
	{
		if (array_key_exists($key, $this->data)) {
			return $this->data[$key];
		}

		throw new MemberAccessException("Cannot read an undeclared item '$key'.");
	}

	public function __isset($key)
	{
		if (array_key_exists($key, $this->data)) {
			return isset($this->data[$key]);
		}

		return FALSE;
	}

	public function __unset($key)
	{
		throw new NotSupportedException('Document is read-only.');
	}

}
