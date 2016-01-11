<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

use Mva\Dbm\Query\Query,
	Mva\Dbm\Query\QueryBuilder,
	Mva\Dbm\Query\QueryWriteBatch,
	Mva\Dbm\Query\QueryProcessor,
	Mva\Dbm\Collection\Selection;

/**
 * Connection is inspired by https://github.com/nextras/dbal by Jan Skrasek
 * @property-read Query\IQuery $query
 * @property-read Driver\IDriver $driver
 */
class Connection
{

	/** @var Query */
	private $query;

	/** @var array */
	private $config;

	/** @var bool */
	private $connected;

	/** @var Driver\IDriver */
	private $driver;

	/** @var Query\QueryProcessor */
	private $preprocessor;

	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver($config);
		$this->preprocessor = new QueryProcessor($this->driver);
	}

	/**
	 * Connects to a database
	 * @return void
	 * @throws ConnectionException
	 */
	public function connect()
	{
		if ($this->connected) {
			return;
		}

		$this->driver->connect($this->config);
		$this->connected = TRUE;
	}

	/**
	 * Returns driver instance
	 * @return Driver\IDriver	 
	 */
	public function getDriver()
	{
		return $this->driver;
	}

	/**
	 * Returns selected database
	 * @return string
	 */
	public function getDatabaseName()
	{
		return $this->driver->getDatabaseName();
	}

	/**
	 * Returns parameter builder
	 * @return Query
	 */
	public function getQuery()
	{
		if (!$this->query) {
			$this->query = new Query($this->driver, $this->preprocessor);
		}

		return $this->query;
	}

	/**
	 * Returns collection
	 * @param string Collection name
	 * @return Selection
	 */
	public function getSelection($name)
	{
		return new Selection($this, $name);
	}

	/**
	 * Returns parameter builder
	 * @return QueryBuilder
	 */
	public function createQueryBuilder()
	{
		return new QueryBuilder();
	}

	/**
	 * Returns write batch
	 * @return QueryWriteBatch
	 */
	public function createWriteBatch()
	{
		return new QueryWriteBatch($this->preprocessor);
	}

	/**
	 * Creates a IDriver instance.
	 * @param  array $config
	 * @return Driver\IDriver
	 */
	private function createDriver(array $config)
	{
		if (empty($config['driver'])) {
			throw new InvalidStateException('Undefined driver');
		} elseif ($config['driver'] instanceof IDriver) {
			return $config['driver'];
		} else {
			$name = ucfirst($config['driver']);
			$class = "Mva\\Dbm\\Driver\\{$name}\\{$name}Driver";
			return new $class;
		}
	}

}
