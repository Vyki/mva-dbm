<?php

namespace Mva\Dbm\Driver\Mongo\Batch;

use MongoInsertBatch,	
	Mva\Dbm\NotSupportedException;

/**
 * @author Roman Vykuka
 */
class InsertBatch extends WriteBatch
{

	protected function createBatch($name)
	{
		return new MongoInsertBatch($this->driver->getCollection($name));
	}

	public function add(array $item)
	{
		$this->queue[] = $this->preprocessor->processData($item);
	}

	protected function finishItem()
	{
		
	}

	public function where($condition, $parameters = [])
	{
		throw new NotSupportedException('It is not possible to parameterize the insert operation!');
	}

}
