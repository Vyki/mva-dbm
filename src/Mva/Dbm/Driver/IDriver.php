<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver;

interface IDriver
{

	const TYPE_OID = 'oid';
	const TYPE_BINARY = 'bin';
	const TYPE_REGEXP = 're';
	const TYPE_TIMESTAMP = 'ts';
	const TYPE_DATETIME = 'dt';

	function connect(array $config);

	function disconnect();

	function getResource();

	function getQuery();

	function getQueryBuilder();

	function convertToPhp($item);

	function convertToDriver($value, $type);
}
