<?php
namespace Mva\Dbm\Driver\Mongo\Batch;

use MongoDeleteBatch;

class DeleteBatch extends WriteBatch
{
	const LIMIT = 'limit';
	
	protected function createBatch($name)
	{
		return new MongoDeleteBatch($this->driver->getCollection($name));
	}
	
	public function add($condition = NULL, $limit = 1)
	{
		$this->finishItem();

		$this->builder = $this->driver->getQueryBuilder();

		$condition && $this->where($condition);
		
		$this->flag(self::LIMIT, $limit);

		return $this;
	}

	protected function finishItem()
	{
		if (!$this->builder) {
			return;
		}

		$this->query[self::QUERY] = $this->preprocessor->processCondition($this->builder->getWhere());
		$this->queue[] = $this->query;
		$this->nextQuery();
	}
}