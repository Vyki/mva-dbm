<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	MongoDB,
	MongoClient,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResultFactory,
	Mva\Dbm\Platform\Mongo\MongoQueryBuilder,
	Mva\Dbm\Platform\Mongo\MongoResultFactory,
	Mva\Dbm\Platform\Mongo\MongoQueryProcessor;

/**
 * @property-read MongoDB $resource
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

	/** @var IResultFactory */
	private $resultFactory;

	/** @var MongoQuery */
	private $query;

	public function __construct()
	{
		$this->preprocessor = new MongoQueryProcessor($this);
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
			$dsn .= ':' . (isset($config['port']) ? $config['port'] : MongoClient::DEFAULT_PORT);

			$this->connection = new MongoClient($dsn);
		}

		if (isset($config['resultFactory']) && $config['resultFactory'] instanceof IResultFactory) {
			$this->resultFactory = $config['resultFactory'];
		}

		$this->database = $this->connection->selectDB($config['database']);
	}

	public function disconnect()
	{
		$this->connection->close();
	}

	public function getResource()
	{
		return $this->database;
	}

	public function getQueryBuilder()
	{
		return new MongoQueryBuilder();
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getResultFactory()
	{
		if (!$this->resultFactory) {
			$this->resultFactory = new MongoResultFactory($this);
		}

		return $this->resultFactory;
	}

	public function getPreprocessor()
	{
		return $this->preprocessor;
	}

	public function convertToPhp($item)
	{
		if ($item instanceof \MongoDate || $item instanceof \MongoTimestamp) {
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

		if ($type === IDriver::TYPE_TIMESTAMP) {
			$value = $value instanceof \DateTimeInterface ? $value->format('U') : $value;
			return new \MongoTimestamp((string) $value);
		}

		if ($type === IDriver::TYPE_BINARY) {
			return new \MongoBinData((string) $value);
		}

		return $value;
	}

}
