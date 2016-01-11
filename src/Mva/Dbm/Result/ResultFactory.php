<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Result;

use Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResultFactory;

class ResultFactory implements IResultFactory
{

	/** @var IDriver */
	private $driver;

	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}

	public function create($data)
	{
		return new Result($this->driver, $data);
	}

}
