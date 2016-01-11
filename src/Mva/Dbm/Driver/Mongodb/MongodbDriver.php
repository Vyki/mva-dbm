<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongodb;

use MongoDB,
	Mva\Dbm\Driver\IDriver;

/**
 * @property-read MongoDB\Driver\Manager $resource
 * @property-read MongoQuery $queryAdapter
 * @property-read Builder $queryBuilder
 * @property-read MongoQueryProcessor $preprocessor
 */
class MongodbDriver implements IDriver
{

	/** @var MongoDB\Driver\Manager */
	private $manager;

	/** @var string */
	private $dbname;

	/** @var MongodbWriteBatch */
	private $writeBatch;

	/** @var MongodbQueryAdapter */
	private $queryAdapter;

	public function connect(array $config)
	{
		if (isset($config['client']) && $config['client'] instanceof MongoDB\Driver\Manager) {
			$this->manager = $config['client'];
		} else {
			$dsn = 'mongodb://';
			$dsn .= isset($config['user']) ? (isset($config['password']) ? $config['user'] . ':' . $config['password'] : $config['user']) . '@' : '';
			$dsn .= isset($config['server']) ? $config['server'] : 'localhost';
			$dsn .= ':' . (isset($config['port']) ? $config['port'] : 27017);

			$this->manager = new MongoDB\Driver\Manager($dsn);
		}

		$this->dbname = $config['database'];
	}

	public function disconnect()
	{
		return NULL;
	}

	/** @return MongoDB\Driver\Manager */
	public function getResource()
	{
		return $this->manager;
	}

	/** @return MongodbQueryAdapter */
	public function getQueryAdapter()
	{
		if (!$this->queryAdapter) {
			$this->queryAdapter = new MongodbQueryAdapter($this);
		}

		return $this->queryAdapter;
	}

	/** @return MongodbWriteBatch */
	public function getWriteBatch()
	{
		if (!$this->writeBatch) {
			$this->writeBatch = new MongodbWriteBatch($this);
		}

		return $this->writeBatch;
	}

	public function getDatabaseName()
	{
		return $this->dbname;
	}

	public function execute($arg1, $arg2 = NULL, $arg3 = NULL)
	{
		if ($arg1 instanceof MongoDB\Driver\Command) {
			return $this->manager->executeCommand($this->dbname, $arg1);
		}

		if ($arg2 instanceof MongoDB\Driver\Query) {
			return $this->manager->executeQuery($this->dbname . '.' . $arg1, $arg2, $arg3);
		}

		if ($arg2 instanceof MongoDB\Driver\BulkWrite) {
			return $this->manager->executeBulkWrite($this->dbname . '.' . $arg1, $arg2, $arg3);
		}

		throw new \Mva\Dbm\InvalidArgumentException(self::class . '::execute called with invalid parameters!');
	}

	public function convertToPhp($item)
	{
		if ($item instanceof MongoDB\BSON\UTCDatetime) {
			return new \DateTime('@' . (string) $item);
		}
		if ($item instanceof MongoDB\BSON\ObjectID) {
			return (string) $item;
		}

		return $item;
	}

	public function convertToDriver($value, $type)
	{
		if ($type === IDriver::TYPE_OID) {
			return new MongoDB\BSON\ObjectID((string) $value);
		}

		if ($type === IDriver::TYPE_REGEXP) {
			return new MongoDB\BSON\Regex((string) $value);
		}

		if ($type === IDriver::TYPE_DATETIME) {
			$value = $value instanceof \DateTimeInterface ? $value->format('U') : $value;
			return new MongoDB\BSON\UTCDatetime((string) $value);
		}

		if ($type === IDriver::TYPE_BINARY) {
			return new MongoDB\BSON\Binary((string) $value);
		}

		return $value;
	}

}
