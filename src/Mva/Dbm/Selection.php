<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

use Nette;

/**
 * Filtered collection representation.
 * Collection is based on the library Nette\Database https://github.com/nette/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 */
class Selection extends Nette\Object implements \IteratorAggregate, \Countable, \ArrayAccess
{

	/** @var string */
	protected $primary = '_id';

	/** @var string */
	protected $primaryModifier = '%oid';

	/** @var Document modifiable data in [primary key => Document] format */
	public $data;

	/** @var \Generator */
	protected $result;

	/** @var Connection */
	protected $connection;

	/** @var Driver\IQueryBuilder */
	protected $queryBuilder;

	public function __construct(Connection $connection, $name)
	{
		$this->connection = $connection;
		$this->queryBuilder = $connection->getQueryBuilder();
		$this->queryBuilder->from($name);
	}

	public function __clone()
	{
		$this->queryBuilder = clone $this->queryBuilder;
	}

	/**
	 * @internal
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->queryBuilder;
	}

	public function getPrimary()
	{
		return $this->primary;
	}

	public function update($data, $upsert = FALSE, $multi = TRUE)
	{
		$data = (array) $data;

		$updated = $this->connection->query->update($this->queryBuilder->from, $data, $this->queryBuilder->where, [
			'multiple' => (bool) $multi, 'upsert' => (bool) $upsert
		]);

		if (isset($updated[$this->primary])) {
			$key = $updated[$this->primary];

			if ($this->data !== NULL) {
				$this[$key] = $updated;
			}

			return $this[$key];
		}

		return $updated;
	}

	/** @return Document|bool */
	public function insert($data)
	{
		$data = (array) $data;

		$inserted = $this->connection->query->insert($this->queryBuilder->from, $data);

		if (isset($inserted[$this->primary])) {
			$key = $inserted[$this->primary];

			if ($this->data !== NULL) {
				$this->data[$key] = $inserted;
			}

			return $this[$key];
		}

		return FALSE;
	}

	public function delete()
	{
		return $this->connection->query->delete($this->queryBuilder->from, $this->queryBuilder->where);
	}

	/**
	 * Adds where condition, more calls appends with AND.
	 * @param string condition possibly containing ?
	 * @param mixed
	 * @return self
	 */
	public function where($condition, $parameters = [])
	{
		$this->emptyResultSet();
		$this->queryBuilder->addWhere($condition, $parameters);
		return $this;
	}

	public function wherePrimary($key)
	{
		$this->where($this->primary . ' = ' . $this->primaryModifier, $key);
		return $this;
	}

	/**
	 * Adds select item, more calls appends to the end.
	 * @param string|array
	 * @return self
	 */
	public function select($items)
	{
		$this->emptyResultSet();

		$this->queryBuilder->addSelect(func_get_args());
		return $this;
	}

	/**
	 * Adds order clause, more calls appends to the end.
	 * @param strings for example 'item1 ASC', 'item2 DESC'
	 * @return self
	 */
	public function order($items)
	{
		$this->emptyResultSet();

		$this->queryBuilder->addOrder(func_get_args());
		return $this;
	}

	/**
	 * Sets limit, more calls rewrite old values.
	 * @param int
	 * @param int
	 * @return self
	 */
	public function limit($limit, $offset = NULL)
	{
		$this->emptyResultSet();

		$this->queryBuilder->limit($limit);
		($offset !== NULL) && $this->queryBuilder->offset($offset);
		return $this;
	}

	################## aggregation ##################

	/**
	 * Counts number of documents.
	 * @return int
	 */
	public function count($column = NULL)
	{
		if (!$column) {
			$this->execute();
			return count($this->data);
		}

		return $this->sum($column);
	}

	/**
	 * Sets group clause, more calls rewrite old value.
	 * @param string
	 * @return self
	 */
	public function group($items)
	{
		$this->emptyResultSet();
		$this->queryBuilder->group(func_get_args());
		return $this;
	}

	/**
	 * Sets having clause, more calls rewrite old value.
	 * @param string condition, for example fruit <> apple
	 * @param mixed
	 * @return self
	 */
	public function having($condition, $parameter = [])
	{
		$this->emptyResultSet();
		$this->queryBuilder->addHaving($condition, $parameter);
		return $this;
	}

	/**
	 * Executes aggregation function.
	 * @param string name of aggregation function
	 * @param string name of field
	 * @return mixed
	 */
	public function aggregate($type, $item)
	{
		$selection = new Selection($this->connection, $this->queryBuilder->from);

		$selection->queryBuilder->importConditions($this->queryBuilder);

		$selection->select("$type($item) AS _gres");

		if (($result = $selection->fetch()) && isset($result->_gres)) {
			return $result->_gres;
		}

		return NULL;
	}

	/**
	 * Returns sum of values.
	 * @param string
	 * @return int
	 */
	public function sum($item)
	{
		return $this->aggregate('sum', $item);
	}

	/**
	 * Returns maximum value from a item.
	 * @param string
	 * @return int
	 */
	public function max($item)
	{
		return $this->aggregate('max', $item);
	}

	/**
	 * Returns minimum value from a item.
	 * @param string
	 * @return int
	 */
	public function min($item)
	{
		return $this->aggregate('min', $item);
	}

	################## quick access ##################

	/** @return Document */
	public function fetch()
	{
		$this->execute();
		$return = current($this->data);
		next($this->data);
		return $return;
	}

	/**
	 * @param  string|NULL $key
	 * @param  string|NULL $value
	 * @return array
	 */
	public function fetchPairs($key = NULL, $value = NULL)
	{
		if ($key === NULL && $value === NULL) {
			throw new InvalidArgumentException('Selection::fetchPairs() requires defined key or value.');
		}

		$return = [];

		if ($key === NULL) {
			foreach ($this as $row) {
				$return[] = $row->{$value};
			}
		} elseif ($value === NULL) {
			foreach ($this as $row) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row;
			}
		} else {
			foreach ($this as $row) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row->{$value};
			}
		}

		return $return;
	}

	public function fetchAssoc($path)
	{
		return Nette\Utils\Arrays::associate($this->fetchAll(), $path);
	}

	/** @return Document[] */
	public function fetchAll()
	{
		return iterator_to_array($this);
	}

	##################  internal ##################

	protected function createDocument(array $doc)
	{
		return new Document($doc);
	}

	protected function execute()
	{
		if ($this->data !== NULL) {
			return;
		}

		$this->data = [];

		list($fields, $criteria, $options) = $this->queryBuilder->buildSelectQuery();

		$result = $this->connection->query->select($this->queryBuilder->from, $fields, $criteria, $options);

		foreach ($result as $key => $doc) {
			$this->data[$key] = $this->createDocument($doc);
		}
	}

	protected function emptyResultSet()
	{
		$this->data = NULL;
	}

	##################  interface Iterator ##################

	public function getIterator()
	{
		$this->execute();
		return $this->createDocumentGenerator();
	}

	##################  document Generator ##################

	private function createDocumentGenerator()
	{
		foreach ($this->data as $key => $value) {
			yield $key => $value;
		}
	}

	################## interface ArrayAccess ##################

	/**
	 * Set document.
	 * @param  string document ID
	 * @param  Document
	 * @return NULL
	 */
	public function offsetSet($key, $value)
	{
		$this->execute();
		$this->data[$key] = $value instanceof Document ? $value : $this->createDocument($value);
	}

	/**
	 * Returns specified document.
	 * @param  string document ID
	 * @return Document or NULL if there is no such document
	 */
	public function offsetGet($key)
	{
		$this->execute();
		return $this->data[$key];
	}

	/**
	 * Tests if document exists.
	 * @param  string document ID
	 * @return bool
	 */
	public function offsetExists($key)
	{
		$this->execute();
		return isset($this->data[$key]);
	}

	/**
	 * Removes document from result set.
	 * @param  string document ID
	 * @return NULL
	 */
	public function offsetUnset($key)
	{
		$this->execute();
		unset($this->data[$key]);
	}

}
