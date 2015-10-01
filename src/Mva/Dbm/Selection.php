<?php

namespace Mva\Dbm;

use Nette,
	MongoCursor;

/**
 * Filtered collection representation.
 * Collection is based on the library Nette\Database http://doc.nette.org/en/2.3/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @author Roman Vykuka
 * 
 */
class Selection extends Nette\Object implements \Iterator, \ArrayAccess, \Countable
{

	/** @var string */
	protected $primary = '_id';

	/** @var MongoCursor|NULL|array */
	protected $result;

	/** @var Document data read from database in [primary key => Document] format */
	protected $docs;

	/** @var Document modifiable data in [primary key => Document] format */
	public $data;

	/** @var array of primary key values */
	protected $keys = [];

	/** @var Connection */
	protected $connection;

	/** @var Driver\Mongo\MongoQueryBuilder */
	protected $queryBuilder;

	public function __construct(Connection $connection, $name)
	{
		$this->connection = $connection;
		$this->queryBuilder = $connection->getQueryBuilder();
		$this->queryBuilder->setFrom($name);
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
		return $this->connection->query->update($this->queryBuilder->from, $data, $this->queryBuilder->where, [
					'multiple' => (bool) $multi, 'upsert' => (bool) $upsert
		]);
	}

	/** @return Document|bool */
	public function insert($data)
	{
		$data = (array) $data;

		$ret = $this->connection->query->insert($this->queryBuilder->from, $data);

		if ($ret && isset($data[$this->primary])) {
			$doc = $this->createDocument($data);

			if ($this->docs !== NULL) {
				$this->docs[$data[$this->primary]] = $doc;
				$this->data[$data[$this->primary]] = $doc;
			}

			return $doc;
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
		$this->where($this->getPrimary(), $key);
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
	 * Remove select item.
	 * @param string|array
	 * @return self
	 */
	public function unselect($items)
	{
		$this->emptyResultSet();

		$this->queryBuilder->addUnselect(func_get_args());
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

		$this->queryBuilder->setLimit($limit);
		($offset !== NULL) && $this->queryBuilder->setOffset($offset);
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
		$this->queryBuilder->setGroup(func_get_args());
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
		if ($this->docs !== NULL) {
			return;
		}

		$this->docs = [];

		list($fields, $criteria, $options) = $this->queryBuilder->buildSelectQuery();

		$result = $this->connection->query->select($this->queryBuilder->from, $fields, $criteria, $options);

		foreach ($result as $index => $doc) {
			$this->docs[$index] = $this->createDocument($doc);
		}

		$this->data = $this->docs;
	}

	protected function emptyResultSet()
	{
		$this->docs = NULL;
	}

	##################  interface Iterator ##################

	public function rewind()
	{
		$this->execute();
		$this->keys = array_keys($this->data);
		reset($this->keys);
	}

	/** @return Document */
	public function current()
	{
		if (($key = current($this->keys)) !== FALSE) {
			return $this->data[$key];
		} else {
			return FALSE;
		}
	}

	/**
	 * @return string row ID
	 */
	public function key()
	{
		return current($this->keys);
	}

	public function next()
	{
		next($this->keys);
	}

	public function valid()
	{
		return current($this->keys) !== FALSE;
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
		$this->docs[$key] = $value;
	}

	/**
	 * Returns specified document.
	 * @param  string document ID
	 * @return Document or NULL if there is no such document
	 */
	public function offsetGet($key)
	{
		$this->execute();
		return $this->docs[$key];
	}

	/**
	 * Tests if document exists.
	 * @param  string document ID
	 * @return bool
	 */
	public function offsetExists($key)
	{
		$this->execute();
		return isset($this->docs[$key]);
	}

	/**
	 * Removes document from result set.
	 * @param  string document ID
	 * @return NULL
	 */
	public function offsetUnset($key)
	{
		$this->execute();
		unset($this->docs[$key], $this->data[$key]);
	}

}
