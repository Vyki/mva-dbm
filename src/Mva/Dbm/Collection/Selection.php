<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Collection;

use Mva\Dbm\Connection,
	Mva\Dbm\Query\Query,
	Mva\Dbm\Query\QueryBuilder,
	Mva\Dbm\Result\Document\DocumentFactory;

/**
 * Filtered collection representation.
 * Collection is based on the library Nette\Database https://github.com/nette/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 */
class Selection implements \IteratorAggregate, \Countable
{

	/** @var string */
	protected $name;

	/** @var Query */
	protected $query;

	/** @var \Generator */
	protected $result;

	/** @var \Generator */
	protected $documents;

	/** @var Connection */
	protected $connection;

	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var DocumentFactory */
	protected $documentFactory;

	/** @var string */
	protected $primaryKey = '_id';

	/** @var string */
	protected $primaryModifier = '%oid';

	public function __construct(Connection $connection, $name)
	{

		$this->name = $name;
		$this->connection = $connection;
		$this->query = $connection->getQuery();
		$this->queryBuilder = $connection->createQueryBuilder();
	}

	public function __clone()
	{
		$this->queryBuilder = clone $this->queryBuilder;
	}

	################################ getters and setters ################################

	/**
	 * @return string
	 */
	public function getPrimary()
	{
		return $this->primaryKey;
	}

	/**
	 * @param string
	 * @param string
	 */
	public function setPrimary($key, $modifier = NULL)
	{
		$this->primaryKey = (string) $key;
		$modifier && $this->primaryModifier = (string) $modifier;
	}

	/**
	 * @return Document\IDocumentFactory	 
	 */
	public function getDocumentFactory()
	{
		if (!$this->documentFactory) {
			$this->documentFactory = new Document\DocumentFactory();
		}

		return $this->documentFactory;
	}

	/**
	 * @param Document\IDocumentFactory	 
	 */
	public function setDocumentFactory(Document\IDocumentFactory $factory)
	{
		$this->documentFactory = $factory;
	}

	/**
	 * @internal
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->queryBuilder;
	}

	################################ querying ################################

	/** 	
	 * @return int|Document number of changed documents or upserted Document object
	 */
	public function update($data, $upsert = FALSE, $multi = TRUE)
	{
		$wdata = (array) $data;

		$updated = $this->query->update($this->name, $wdata, $this->queryBuilder->where, (bool) $upsert, (bool) $multi);

		if ($upsert && isset($updated[$this->primaryKey])) {
			$doc = $this->createDocument($updated);
			$this->result && array_push($this->result, $doc);
			return $doc;
		}

		return $updated;
	}

	/**
	 * @return Document|bool inserted Document object or FALSE
	 */
	public function insert($data)
	{
		$wdata = (array) $data;

		$inserted = $this->query->insert($this->name, $wdata);

		if (isset($inserted[$this->primaryKey])) {
			$doc = $this->createDocument($inserted);
			$this->result && array_push($this->result, $doc);
			return $doc;
		}

		return $inserted;
	}

	public function delete($multi = TRUE)
	{
		return $this->query->delete($this->name, $this->queryBuilder->where, $multi);
	}

	/**
	 * Adds where condition, more calls appends with AND.
	 * @param string condition possibly containing %modifier
	 * @param mixed
	 * @return self
	 */
	public function where($condition, $parameters = [])
	{
		$this->emptyResult();

		$this->queryBuilder->addWhere($condition, $parameters);
		return $this;
	}

	/**
	 * Condition for finding by primary key.
	 * @param string primary key
	 * @return self	 
	 */
	public function wherePrimary($key)
	{
		$this->where($this->primaryKey . ' = ' . $this->primaryModifier, $key);
		return $this;
	}

	/**
	 * Adds select item, more calls appends to the end.
	 * @param string
	 * @return self
	 */
	public function select($items)
	{
		$this->emptyResult();

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
		$this->emptyResult();

		empty($items) ? $this->queryBuilder->order(NULL) : $this->queryBuilder->addOrder(func_get_args());
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
		$this->emptyResult();

		$this->queryBuilder->limit($limit, $offset);
		return $this;
	}

	################## aggregation ##################

	/**
	 * Counts number of documents.
	 * @return int
	 */
	public function count($column = NULL)
	{
		if ($column) {
			return $this->sum($column);
		}

		if ($this->result) {
			return count($this->result);
		}

		list(, $criteria, $options) = $this->queryBuilder->buildSelectQuery();

		return $this->query->count($this->name, $criteria, $options);
	}

	/**
	 * Sets group clause, more calls rewrite old value.
	 * @param string
	 * @return self
	 */
	public function group($items)
	{
		$this->emptyResult();
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
		$this->emptyResult();
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
		$selection = $this->createSelectionInstance();

		$selection->getQueryBuilder()->importConditions($this->queryBuilder);

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

	/**
	 * Returns row specified by primary key.
	 * @param  mixed primary key
	 * @return Document or FALSE if there is no such document
	 */
	public function get($key)
	{
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}

	/** @return Document */
	public function fetch()
	{
		$this->execute();
		$return = current($this->result);
		next($this->result);
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

	/**
	 * @return Document[] 
	 */
	public function fetchAll()
	{
		return iterator_to_array($this);
	}

	##################  internal ##################

	/**
	 * @return Document
	 */
	protected function createDocument(array $data)
	{
		return $this->documentFactory === FALSE ? $data : $this->getDocumentfactory()->create($data);
	}

	/**
	 * @return Selection	 
	 */
	public function createSelectionInstance($collection = NULL)
	{
		return new Selection($this->connection, $collection ? : $this->name);
	}

	/**
	 * @return void 
	 */
	protected function execute()
	{
		if ($this->result !== NULL) {
			return;
		}

		$this->result = [];

		list($fields, $criteria, $options) = $this->queryBuilder->buildSelectQuery();

		$result = $this->query->find($this->name, $fields, $criteria, $options);

		while ($data = $result->fetch()) {
			$this->result[] = $this->createDocument($data);
		}
	}

	protected function emptyResult()
	{
		$this->result = NULL;
	}

	##################  interface Iterator ##################

	/**
	 * @return \Generator	 
	 */
	public function getIterator()
	{
		$this->execute();
		return $this->createDocumentGenerator();
	}

	##################  document Generator ##################

	/**
	 * @return \Generator	 
	 */
	private function createDocumentGenerator()
	{
		foreach ($this->result as $value) {
			yield $value;
		}
	}

}
