<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Result;

use Mva\Dbm\Driver\IDriver;

interface IResultFactory
{

	/** @return IResult */
	function create($data);

	function setDriver(IDriver $driver);
}
