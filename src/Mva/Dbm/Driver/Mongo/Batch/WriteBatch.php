<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo\Batch;

use Nette,
	MongoWriteBatch,
	Mva\Dbm\Driver\Mongo\MongoDriver,
	Mva\Dbm\Driver\Mongo\MongoQueryBuilder,
	Mva\Dbm\Driver\Mongo\MongoQueryProcessor;

abstract class WriteBatch extends Nette\Object
{

	const QUERY = 'q';

	/** @var MongoDriver */
	protected $driver;

	/** @var MongoQueryProcessor */
	protected $preprocessor;

	/** @var MongoWriteBatch */
	protected $batch;

	/** @var MongoQueryBuilder */
	protected $builder;

	/** @var array */
	protected $queue;

	/** @var array */
	protected $query;

	/** @var string collection name */
	protected $collection;

	public function __construct(MongoDriver $driver, $collection)
	{
		$this->driver = $driver;
		$this->collection = $collection;
		$this->preprocessor = $driver->getPreprocessor();
	}

	public function where($condition, $parameters = [])
	{
		$this->builder->addWhere($condition, $parameters);
	}

	public function flag($type, $value)
	{
		$this->query[$type] = $value;
		return $this;
	}

	public function reset()
	{
		$this->queue = [];
		$this->nextQuery();
	}

	protected function nextQuery()
	{
		$this->query = [];
		$this->builder = NULL;
	}

	/** @internal */
	public function getQueue()
	{
		$this->finishItem();
		return $this->queue;
	}

	public function execute()
	{
		$this->finishItem();

		$batch = $this->createBatch($this->collection);

		foreach ($this->queue as $item) {
			$batch->add($item);
		}

		$this->reset();

		return $batch->execute(['w' => 1]);
	}

	abstract protected function createBatch($name);

	abstract protected function finishItem();
}
