<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query;

use Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Query\QueryProcessor;

class QueryWriteBatch
{

	const INSERT = 'insert';
	const UPDATE = 'update';
	const DELETE = 'delete';
	const LIMIT = 'limit';
	const UPSERT = 'upsert';
	const MULTIPLE = 'multi';

	/** @var array */
	protected $queue;

	/** @var IDriver */
	protected $driver;

	/** @var MongoQueryProcessor */
	protected $preprocessor;

	public function __construct(QueryProcessor $preprocessor)
	{
		$this->reset();
		$this->preprocessor = $preprocessor;
	}

	public function delete(array $criteria, $multi = TRUE)
	{
		$item = &$this->queue[self::DELETE][];

		$item[0] = $this->preprocessor->processCondition($criteria);
		$item[1] = [self::LIMIT => $multi ? 0 : 1];

		return $this;
	}

	public function insert(array $data)
	{
		$this->queue[self::INSERT][] = $this->preprocessor->processData($data, TRUE);
		return $this;
	}

	public function update(array $data, array $criteria, $upsert = FALSE, $multi = TRUE)
	{
		$item = &$this->queue[self::UPDATE][];

		$item[0] = $this->preprocessor->processCondition($criteria);
		$item[1] = $this->preprocessor->processUpdate($data);
		$item[2] = [
			self::UPSERT => (bool) $upsert,
			self::MULTIPLE => (bool) $multi
		];

		return $this;
	}

	public function getQueue($op = NULL)
	{
		return $op && isset($this->queue[$op]) ? $this->queue[$op] : $this->queue;
	}

	public function reset()
	{
		$this->queue = [self::INSERT => [], self::UPDATE => [], self::DELETE => []];
	}

}
