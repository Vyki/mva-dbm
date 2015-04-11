<?php

namespace Mva\Mongo;

use Nette,
	MongoDB,
	MongoCursor,
	Nette\Utils\Strings;

/**
 * Filtered collection representation.
 * Collection is based on the library Nette\Database http://doc.nette.org/en/2.3/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @author Roman Vykuka
 *
 */
class Collection extends Nette\Object implements \Iterator, \ArrayAccess, \Countable
{

	/** @var string */
	protected $primary = '_id';

	/** @var string */
	protected $name;

	/** @var \MongoCursor|NULL|array */
	protected $result;

	/** @var Document data read from database in [primary key => Document] format */
	protected $docs;

	/** @var Document modifiable data in [primary key => Document] format */
	public $data;

	/** @var array of primary key values */
	protected $keys = array();

	/** @var MongoDB */
	protected $database;

	/** @var ParamBuilder */
	protected $paramBuilder;

	public function __construct($name, MongoDB $mongo)
	{
		$this->name = $name;
		$this->database = $mongo;
		$this->paramBuilder = new ParamBuilder;
	}

	public function __clone()
	{
		$this->paramBuilder = clone $this->paramBuilder;
	}

	/**
	 * @internal
	 * @return ParamBuilder
	 */
	public function getParamBuilder()
	{
		return $this->paramBuilder;
	}

	public function getPrimary()
	{
		return $this->primary;
	}

	public function update($data, $upsert = FALSE, $multi = TRUE)
	{
		$data['$set'] = isset($data['$set']) ? $data['$set'] : array();

		foreach ($data as $index => $value) {
			if (!Strings::startsWith($index, '$')) {
				$data['$set'][$index] = $value;
				unset($data[$index]);
			} elseif ($index === '$unset') {
				$data['$unset'] = array_fill_keys(array_values((array) $data['$unset']), '');
			}
		}

		if (empty($data['$set'])) {
			unset($data['$set']);
		}

		return $this->database->selectCollection($this->name)->update(
						$this->paramBuilder->where, $data, array('multiple' => (bool) $multi, 'upsert' => (bool) $upsert)
		);
	}

	public function insert($data)
	{
		$ret = $this->database->selectCollection($this->name)->insert($data);

		if ($ret && isset($data['_id'])) {
			if ($this->docs !== NULL) {
				$this->docs[$data['_id']] = $data;
				$this->data[$data['_id']] = $data;
			}

			return $data;
		}

		return FALSE;
	}

	public function delete()
	{
		return $this->database->selectCollection($this->name)->remove($this->paramBuilder->where);
	}

	/**
	 * Adds where condition, more calls appends with AND.
	 * @param string condition possibly containing ?
	 * @param mixed
	 * @return self
	 */
	public function where($condition, $parameters = array())
	{
		if (is_array($condition) && $parameters === array()) {
			foreach ($condition as $key => $val) {
				if (is_int($key)) {
					$this->where($val);
				} else {
					$this->where($key, $val);
				}
			}
			return $this;
		}

		$this->emptyResultSet();
		$this->paramBuilder->addWhere($condition, $parameters);
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

		$this->paramBuilder->addSelect(func_get_args());
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

		$this->paramBuilder->addUnselect(func_get_args());
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

		$this->paramBuilder->addOrder(func_get_args());
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

		$this->paramBuilder->setLimit($limit);
		$this->paramBuilder->setOffset($offset);
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
		$this->paramBuilder->setGroup(func_get_args());
		return $this;
	}

	/**
	 * Sets having clause, more calls rewrite old value.
	 * @param string condition, for example fruit <> apple
	 * @param mixed
	 * @return self
	 */
	public function having($condition, $parameter = array())
	{
		$this->emptyResultSet();
		$this->paramBuilder->addHaving($condition, $parameter);
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
		$collection = $this->createCollectionInstance();

		$collection->getParamBuilder()->importConditions($this->getParamBuilder());

		$collection->select("$type($item) AS _gres");

		if (($result = $collection->fetch()) && isset($result['_gres'])) {
			return $result['_gres'];
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

	public function fetch()
	{
		$this->execute();
		$return = current($this->data);
		next($this->data);
		return $return;
	}

	public function fetchPairs($key, $data = NULL)
	{
		$ret = array();

		foreach ($this as $doc) {
			$ret[$doc[$key]] = $data ? $doc[$data] : $doc->toArray();
		}

		return $ret;
	}

	public function fetchAssoc($path)
	{
		return Nette\Utils\Arrays::associate(array_map('iterator_to_array', $this->fetchAll()), $path);
	}

	public function fetchAll()
	{
		return iterator_to_array($this);
	}

	##################  internal ##################

	protected function createDocument(array $doc)
	{
		return new Document($doc, $this);
	}

	protected function createAggregatedDocument(array $result)
	{
		return new AggregatedDocument($result, $this);
	}

	public function createCollectionInstance($collection = NULL)
	{
		return new Collection($collection ?: $this->name, $this->database);
	}

	protected function execute()
	{
		if ($this->docs !== NULL) {
			return;
		}

		$this->docs = array();

		if ($this->paramBuilder->aggregate) {
			$query = $this->paramBuilder->buildAggreregateQuery();
			$result = $this->database->selectCollection($this->name)->aggregate($query);

			if ($result['ok'] == 1 && isset($result['result'])) {
				foreach ($result['result'] as $doc) {
					$this->docs[] = $this->createAggregatedDocument($doc);
				}
			}
		} else {
			$query = $this->paramBuilder->buildSelectQuery();
			$result = $this->database->selectCollection($this->name)->find($query[1], $query[0]);

			if ($result instanceof MongoCursor) {
				empty($this->paramBuilder->limit) ?: $result->limit($this->paramBuilder->limit);
				empty($this->paramBuilder->offset) ?: $result->skip($this->paramBuilder->offset);
				empty($this->paramBuilder->order) ?: $result->sort($this->paramBuilder->order);
			}

			foreach ($result as $index => $doc) {
				$this->docs[$index] = $this->createDocument($doc);
			}
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
