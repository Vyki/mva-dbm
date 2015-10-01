<?php

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	MongoDB,
	MongoClient,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Driver\Mongo\MongoQueryBuilder as Builder;

/**
 * @property-read MongoDB $database
 * @property-read MongoQuery $query
 * @property-read Builder $queryBuilder
 * @property-read MongoQueryProcessor $preprocessor
 */
class MongoDriver extends Nette\Object implements IDriver
{

	/** @var MongoClient */
	private $connection;

	/** @var MongoDB */
	private $database;

	/** @var MongoQueryProcessor */
	private $preprocessor;

	/** @var MongoQuery */
	private $query;

	public function __construct()
	{
		$this->preprocessor = new MongoQueryProcessor();
		$this->query = new MongoQuery($this);
	}

	public function connect(array $config)
	{
		if (isset($config['client']) && $config['client'] instanceof MongoClient) {
			$this->connection = $config['client'];
		} else {
			$dsn = 'mongodb://';
			$dsn .= isset($config['user']) ? (isset($config['password']) ? $config['user'] . ':' . $config['password'] : $config['user']) . '@' : '';
			$dsn .= isset($config['server']) ? $config['server'] : MongoClient::DEFAULT_HOST;
			$dsn .= ':' . isset($config['port']) ? $config['port'] : MongoClient::DEFAULT_PORT;

			$this->connection = new MongoClient($dsn);
		}

		$this->database = $this->connection->selectDB($config['database']);
	}

	public function disconnect()
	{
		$this->connection->close();
	}

	public function getDatabase()
	{
		return $this->database;
	}

	public function getCollection($name)
	{
		return $this->database->selectCollection($name);
	}

	public function getQueryBuilder()
	{
		return new MongoQueryBuilder();
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getPreprocessor()
	{
		return $this->preprocessor;
	}

	/**
	 * @return MongoResult 
	 */
	public function select($collection, array $fields = [], array $criteria = [], array $options = [])
	{
		if (isset($fields[0])) {
			return $this->selectAggregate($collection, $fields);
		}

		if (!empty($criteria)) {
			$criteria = $this->preprocessor->processCondition($criteria);
		}

		if (isset($fields[Builder::SELECT_DISTINCT]) && count($fields) === 1 && is_string($fields[Builder::SELECT_DISTINCT])) {
			return $this->selectDistinct($collection, $fields[Builder::SELECT_DISTINCT], $criteria);
		}

		$result = $this->getCollection($collection)->find($criteria, $fields);

		if (isset($options[Builder::SELECT_LIMIT])) {
			$result->limit($options[Builder::SELECT_LIMIT]);
		}

		if (isset($options[Builder::SELECT_OFFSET])) {
			$result->skip($options[Builder::SELECT_OFFSET]);
		}

		if (isset($options[Builder::SELECT_ORDER])) {
			$result->sort($options[Builder::SELECT_ORDER]);
		}

		return new MongoResult($result);
	}

	public function delete($collection, array $criteria, array $options = [])
	{
		return $this->getCollection($collection)->remove($criteria, $options);
	}

	public function insert($collection, array $data, array $options = [])
	{
		return $this->getCollection($collection)->insert($data, $options);
	}

	public function update($collection, array $data, array $criteria, array $options = [])
	{
		$data = $this->preprocessor->processUpdate($data);
		$criteria = $this->preprocessor->processCondition($criteria);

		return $this->getCollection($collection)->update($criteria, $data, $options);
	}

	public function selectAggregate($collection, $pipelines)
	{
		$match = $this->preprocessor->formatCmd('match');

		foreach ($pipelines as &$pipeline) {
			if (isset($pipeline[$match])) {
				$pipeline[$match] = $this->preprocessor->processCondition($pipeline[$match]);
			}
		}

		$result = $this->getCollection($collection)->aggregateCursor($pipelines);

		return new MongoResult($result);
	}

	public function selectDistinct($collection, $item, array $criteria = [])
	{
		$result = new ArrayIterator($this->getCollection($collection)->distinct($item, $criteria));
		return new MongoResult($result);
	}

	public function insertBatch($collection)
	{
		return new Batch\InsertBatch($this, $collection);
	}

	public function updateBatch($collection)
	{
		return new Batch\UpdateBatch($this, $collection);
	}

	public function deleteBatch($collection)
	{
		return new Batch\DeleteBatch($this, $collection);
	}

}
