<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResultFactory;

class MongoResultfactory implements IResultFactory
{

	/** @var IDriver */
	private $driver;

	public function create($data)
	{
		return new MongoResult($data);
	}

	public function setDriver(IDriver $driver)
	{
		$this->driver = $driver;
	}

}
