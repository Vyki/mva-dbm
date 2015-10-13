<?php

namespace Mva\Dbm\Driver\Mongo\Batch;

use MongoUpdateBatch;

/**
 * @author Roman Vykuka
 */
class UpdateBatch extends WriteBatch
{

	const UPDATE = 'u',
			UPSERT = 'upsert',
			MULTIPLE = 'multi';

	protected function createBatch($name)
	{
		return new MongoUpdateBatch($this->driver->getCollection($name));
	}

	public function add(array $data, $upsert = FALSE, $multi = TRUE)
	{
		$this->finishItem();

		$this->flag(self::UPSERT, (bool) $upsert);
		$this->flag(self::MULTIPLE, (bool) $multi);

		$this->builder = $this->driver->getQueryBuilder();
		$this->query[self::UPDATE] = $data;

		return $this;
	}

	protected function finishItem()
	{
		if (!$this->builder) {
			return;
		}
		
		$this->query[self::UPDATE] = $this->preprocessor->processUpdate($this->query[self::UPDATE]);
		$this->query[self::QUERY] = $this->preprocessor->processCondition($this->builder->getWhere());

		$this->queue[] = $this->query;

		$this->nextQuery();
	}

}
