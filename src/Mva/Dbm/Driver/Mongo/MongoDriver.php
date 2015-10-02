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
}
