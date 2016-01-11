<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongodb;

use MongoDB,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Query\IWriteBatch,
	Mva\Dbm\Query\QueryWriteBatch,
	Mva\Dbm\Driver\Mongodb\MongodbDriver,
	Mva\Dbm\Result\ResultWriteBatch as Result;

class MongodbWriteBatch implements IWriteBatch
{

	/** @var IDriver */
	protected $driver;

	/** @var string collection name */
	protected $collection;

	public function __construct(MongodbDriver $driver)
	{
		$this->driver = $driver;
	}

	public function write($collection, QueryWriteBatch $batch)
	{
		$writer = new MongoDB\Driver\BulkWrite();

		$inserted = [];

		foreach ($batch->getQueue(QueryWriteBatch::INSERT) as $index => $data) {
			$oid = $writer->insert($data);
			$inserted[$index] = $oid !== NULL ? $oid : (isset($data[0]['_id']) ? $data[0]['_id'] : NULL);
		}

		foreach ($batch->getQueue(QueryWriteBatch::UPDATE) as $data) {
			$writer->update($data[0], $data[1], $data[2]);
		}

		foreach ($batch->getQueue(QueryWriteBatch::DELETE) as $data) {
			$writer->delete($data[0], $data[1]);
		}

		$result = $this->driver->execute($collection, $writer);

		return [
			Result::UPDATED => $result->getModifiedCount(),
			Result::MATCHED => $result->getMatchedCount(),
			Result::DELETED => $result->getDeletedCount(),
			Result::INSERTED => $result->getInsertedCount(),
			Result::UPSERTED => $result->getUpsertedCount(),
			Result::INSERTED_IDS => $inserted,
			Result::UPSERTED_IDS => $result->getUpsertedIds()
		];
	}

}
