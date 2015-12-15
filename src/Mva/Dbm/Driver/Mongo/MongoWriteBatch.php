<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Mva\Dbm\Platform\Mongo\MongoBulkWrite;

class MongoWriteBatch extends MongoBulkWrite
{

	public function execute()
	{
		$return = [];

		foreach (['insert', 'update', 'delete'] as $op) {
			if (empty($this->queue[$op])) {
				continue;
			}

			$class = "\\Mongo{$op}Batch";

			$batch = new $class($this->driver->resource->selectCollection($this->collection));

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

	public function getUpserted()
	{
		return $this->upserted->fetchPairs('index', '_id');
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
