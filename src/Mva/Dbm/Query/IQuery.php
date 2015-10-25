<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query;

interface IQuery
{

	const SELECT_LIMIT = 'limit';
	const SELECT_OFFSET = 'skip';
	const SELECT_ORDER = 'sort';
	const SELECT_DISTINCT = 'distinct';
	const SELECT_COUNT = 'count';
	const UPDATE_UPSERT = 'upsert';
	const UPDATE_MULTIPLE = 'multiple';
	const DELETE_ONE = 'justOne';

	function select($collection, $fields = [], array $criteria = [], array $options = []);

	function delete($collection, array $criteria, $options = []);

	function insert($collection, array $data, $options = []);

	function update($collection, array $data, array $criteria, $options = [], $multi = TRUE);
}
