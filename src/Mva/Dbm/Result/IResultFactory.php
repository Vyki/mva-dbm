<?php

namespace Mva\Dbm\Result;

use Mva\Dbm\Driver\IDriver;

interface IResultFactory
{

	/** @return IResult */
	function create($data);

	function setDriver(IDriver $driver);
}
