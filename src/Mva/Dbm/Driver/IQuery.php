<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver;

interface IQuery
{

	const SELECT_LIMIT = 'limit';
	const SELECT_OFFSET = 'skip';
	const SELECT_ORDER = 'sort';
	const SELECT_DISTINCT = 'distinct';
	const SELECT_COUNT = 'count';

	function select($collection, $fields = [], array $criteria = [], array $options = []);

	function delete($collection, array $criteria, array $options = []);

	function insert($collection, array $data, array $options = []);

	function update($collection, array $data, array $criteria, array $options = []);
}
