<?php

namespace Mva\Dbm\Driver\Mongo\Batch;

use Nette,
	MongoClient,
	MongoWriteBatch,
	Mva\Dbm\NotSupportedException,
	Mva\Dbm\Driver\Mongo\MongoDriver,
	Mva\Dbm\Driver\Mongo\MongoQueryBuilder,
	Mva\Dbm\Driver\Mongo\MongoQueryProcessor;

/**
 * @author Roman Vykuka
 */
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
		if (version_compare(MongoClient::VERSION, '1.5.0') < 0) {
			throw new NotSupportedException('Write batch is not available in your version of the PHP Mongo extension. Update it to 1.5.0 or newer.');
		}

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
