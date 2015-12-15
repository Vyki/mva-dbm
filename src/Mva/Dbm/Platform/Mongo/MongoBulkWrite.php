<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Platform\Mongo;

use Nette,
	Mva\Dbm\Driver\IDriver;

abstract class MongoBulkWrite extends Nette\Object
{

	const LIMIT = 'limit';
	const UPDATE = 'u';
	const UPSERT = 'upsert';
	const MULTIPLE = 'multi';
	const CRITERIA = 'q';
	const DATA = 'data';

	/** @var IDriver */
	protected $driver;

	/** @var MongoQueryProcessor */
	protected $preprocessor;

	/** @var array */
	protected $queue = ['insert' => [], 'update' => [], 'delete' => []];

	/** @var array */
	protected $upserted;

	/** @var string collection name */
	protected $collection;

	public function __construct(IDriver $driver, $collection)
	{
		$this->driver = $driver;
		$this->collection = $collection;
		$this->preprocessor = $driver->getPreprocessor();
	}

	public function insert(array $data)
	{
		$this->queue['insert'][] = $this->preprocessor->processData($data);
		return $this;
	}

	public function update(array $data, array $criteria = [], $upsert = FALSE, $multi = TRUE)
	{
		$item = &$this->queue['update'][];

		$item[self::CRITERIA] = $this->preprocessor->processCondition($criteria);
		$item[self::UPDATE] = $this->preprocessor->processUpdate($data);
		$item[self::UPSERT] = $upsert === NULL ? FALSE : (bool) $upsert;
		$item[self::MULTIPLE] = $multi === NULL ? TRUE : (bool) $multi;

		return $this;
	}

	public function delete(array $criteria = [], $limit = 1)
	{
		$item = &$this->queue['delete'][];

		$item[self::CRITERIA] = $this->preprocessor->processCondition($criteria);
		$item[self::LIMIT] = (int) $limit;

		return $this;
	}

	abstract public function execute();

	abstract public function getUpserted();

	public function getQueue($op = NULL)
	{
		return $op && isset($this->queue[$op]) ? $this->queue[$op] : $this->queue;
	}

	public function reset()
	{
		$this->queue = ['insert' => [], 'update' => [], 'delete' => []];
	}

}
