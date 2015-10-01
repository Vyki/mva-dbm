<?php

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
