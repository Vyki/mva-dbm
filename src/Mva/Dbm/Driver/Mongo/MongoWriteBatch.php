<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	Mva\Dbm\Driver\Mongo\MongoDriver,
	Mva\Dbm\Driver\Mongo\MongoQueryProcessor;

class MongoWriteBatch extends Nette\Object
{

	const LIMIT = 'limit';
	const UPDATE = 'u';
	const UPSERT = 'upsert';
	const MULTIPLE = 'multi';
	const CRITERIA = 'q';
	const DATA = 'data';

	/** @var MongoDriver */
	protected $driver;

	/** @var MongoQueryProcessor */
	protected $preprocessor;

	/** @var array */
	protected $queue = ['insert' => [], 'update' => [], 'delete' => []];

	/** @var array */
	private $upserted;

	/** @var string collection name */
	protected $collection;

	public function __construct(MongoDriver $driver, $collection)
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

	public function execute()
	{
		$return = [];

		foreach (['insert', 'update', 'delete'] as $op) {
			if (empty($this->queue[$op])) {
				continue;
			}

			$class = "\\Mongo{$op}Batch";

			$batch = new $class($this->driver->getCollection($this->collection));

			foreach ($this->queue[$op] as $item) {
				$batch->add($item);
			}

			$result = $batch->execute(['w' => 1]);

			if (isset($result['ok'])) {
				$rows = $this->processResult($result);
				$this->driver->query->onQuery($this->collection, "$op - batch", ['w' => 1], $rows);
				$return = $return + $rows;

				if (isset($result['upserted'])) {
					$this->upserted = $this->driver->resultFactory->create($result['upserted']);
				}
			}
		}

		return $return;
	}

	public function getQueue($op = NULL)
	{
		return $op && isset($this->queue[$op]) ? $this->queue[$op] : $this->queue;
	}

	public function getUpserted()
	{
		return $this->upserted->fetchPairs('index', '_id');
	}

	public function reset()
	{
		$this->queue = ['insert' => [], 'update' => [], 'delete' => []];
	}

	private function processResult($result)
	{
		$return = [];

		foreach ((array) $result as $item => $value) {
			if (substr($item, 0, 1) === 'n') {
				$return[strtolower(substr($item, 1))] = $value;
			}
		}

		return $return;
	}

}
