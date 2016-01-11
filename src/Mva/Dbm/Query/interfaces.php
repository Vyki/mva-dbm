<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query;

use Mva\Dbm\Result\IResult;

interface IQuery
{

	const LIMIT = 'limit';
	const OFFSET = 'skip';
	const ORDER = 'sort';
	const ORDER_ASC = 1;
	const ORDER_DESC = -1;

	/** @return IResult */
	public function find($collection, $fields = [], array $criteria = [], array $options = []);

	/** @return IResult */
	public function distinct($collection, $item, array $criteria = []);

	/** @return int */
	public function count($collection, array $criteria = [], array $options = []);

	/** @return IResult */
	public function aggregate($collection, $pipelines);

	public function insert($collection, array $data);

	public function update($collection, array $data, array $criteria, $upsert = FALSE, $multi = TRUE);

	public function delete($collection, array $criteria, $multi = TRUE);
}

interface IQueryAdapter extends IQuery
{
	
}

interface IWriteBatch
{

	function write($collection, QueryWriteBatch $batch);
}
