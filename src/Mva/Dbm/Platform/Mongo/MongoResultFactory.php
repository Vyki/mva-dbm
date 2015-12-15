<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Platform\Mongo;

use Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResultFactory;

class MongoResultFactory implements IResultFactory
{

	/** @var IDriver */
	private $driver;

	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}

	public function create($data)
	{
		return new MongoResult($this->driver, $data);
	}

}
