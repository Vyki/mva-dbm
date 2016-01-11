<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Result;

interface IResult extends \Traversable
{

	public function fetch();

	public function fetchAll();

	public function fetchField();

	public function fetchPairs($key = NULL, $value = NULL);

	public function getRawResult();
}

interface IResultFactory
{

	/** @return IResult */
	function create($data);
}
