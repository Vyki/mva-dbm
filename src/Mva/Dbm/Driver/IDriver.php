<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver;

interface IDriver
{

	function connect(array $config);

	function disconnect();

	function getDatabase();

	function getCollection($name);

	function getQueryBuilder();

	function getQuery();
}
