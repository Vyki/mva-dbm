<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

use Mva,
	Nette,
	MongoDB,
	MongoCollection,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Collection\Selection;

/**
 * Connection is inspired by https://github.com/nextras/dbal by Jan Skrasek
 * @property-read Driver\IQuery $query
 * @property-read Driver\IDriver $driver
 */
class Connection extends Nette\Object
{

	/** @var array */
	private $config;

	/** @var bool */
	private $connected;

	/** @var Driver\IDriver */
	private $driver;

	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver($config);
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
	 * @return MongoDB
	 */
	public function getDatabase()
	{
		return $this->driver->getDatabase();
	}

	/**
	 * Returns selected collection
	 * @return MongoCollection
	 */
	public function getCollection($name)
	{
		return $this->driver->getCollection($name);
	}

	/**
	 * Returns parameter builder
	 * @return Driver\IQuery
	 */
	public function getQuery()
	{
		return $this->driver->getQuery();
	}

	/**
	 * Returns parameter builder
	 * @return Driver\IQueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->driver->getQueryBuilder();
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
