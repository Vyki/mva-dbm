<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use MongoDB,
	MongoClient,
	Mva\Dbm\Driver\IDriver;

class MongoDriver implements IDriver
{

	/** @var string */
	private $dbname;

	/** @var MongoDB */
	private $database;

	/** @var MongoClient */
	private $connection;
	
	/** @var MongoWriteBatch */
	private $writeBatch;

	/** @var MongoQueryAdapter */
	private $queryAdapter;

	public function connect(array $config)
	{
		if (isset($config['client']) && $config['client'] instanceof MongoClient) {
			$this->connection = $config['client'];
		} else {
			$dsn = 'mongodb://';
			$dsn .= isset($config['user']) ? (isset($config['password']) ? $config['user'] . ':' . $config['password'] : $config['user']) . '@' : '';
			$dsn .= isset($config['server']) ? $config['server'] : MongoClient::DEFAULT_HOST;
			$dsn .= ':' . (isset($config['port']) ? $config['port'] : MongoClient::DEFAULT_PORT);

			$this->connection = new MongoClient($dsn);
		}

		$this->dbname = $config['database'];
		$this->database = $this->connection->selectDB($config['database']);
	}

	public function disconnect()
	{
		$this->connection->close();
	}

	/** @return MongoDB */
	public function getResource()
	{
		return $this->database;
	}

	/** @return MongoQueryAdapter */
	public function getQueryAdapter()
	{
		if (!$this->queryAdapter) {
			$this->queryAdapter = new MongoQueryAdapter($this);
		}

		return $this->queryAdapter;
	}

	public function getDatabaseName()
	{
		return $this->dbname;
	}

	/** @return MongodbWriteBatch */
	public function getWriteBatch()
	{
		if (!$this->writeBatch) {
			$this->writeBatch = new MongoWriteBatch($this);
		}

		return $this->writeBatch;
	}

	public function convertToPhp($item)
	{
		if ($item instanceof \MongoDate) {
			return new \DateTime('@' . (string) $item->sec);
		}
		if ($item instanceof \MongoId) {
			return (string) $item;
		}

		return $item;
	}

	public function convertToDriver($value, $type)
	{
		if ($type === IDriver::TYPE_OID) {
			return new \MongoId((string) $value);
		}

		if ($type === IDriver::TYPE_REGEXP) {
			return new \MongoRegex((string) $value);
		}
		if ($type === IDriver::TYPE_DATETIME) {
			$value = $value instanceof \DateTimeInterface ? $value->format('U') : $value;
			return new \MongoDate((string) $value);
		}

		if ($type === IDriver::TYPE_BINARY) {
			return new \MongoBinData((string) $value);
		}

		return $value;
	}

}
