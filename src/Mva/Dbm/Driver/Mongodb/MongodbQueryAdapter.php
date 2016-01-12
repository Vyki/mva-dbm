<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongodb;

use MongoDB,
	Mva\Dbm\QueryException,
	Mva\Dbm\Query\IQueryAdapter,
	MongoDB\Driver\Exception\RuntimeException;

class MongodbQueryAdapter implements IQueryAdapter
{

	/** @var MongodbDriver */
	private $driver;

	/** @var MongodbWriteBatch */
	private $writeBatch;

	public function __construct(MongodbDriver $driver)
	{
		$this->driver = $driver;
	}

	private function getWriteBatch()
	{
		if (!$this->writeBatch) {
			$this->writeBatch = new MongodbWriteBatch($this->driver);
		}

		return $this->writeBatch;
	}

	public function find($collection, $fields = [], array $criteria = [], array $options = [])
	{
		try {
			$query = new MongoDB\Driver\Query($criteria, array_merge($options, ['projection' => $fields]));

			$cursor = $this->driver->execute($collection, $query);
			$cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		return $cursor;
	}

	public function count($collection, array $criteria = [], array $options = [])
	{
		$params = [
			'count' => $collection,
			'query' => (object) $criteria
		];

		try {
			$command = new MongoDB\Driver\Command(array_merge($params, $options));

			$cursor = $this->driver->execute($command);
			$cursor->setTypeMap(['document' => 'array']);
			$result = current($cursor->toArray());
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		return isset($result->n) ? $result->n : NULL;
	}

	public function aggregate($collection, $pipelines)
	{
		try {
			$command = new MongoDB\Driver\Command([
				'aggregate' => $collection,
				'pipeline' => $pipelines
			]);

			$cursor = $this->driver->execute($command);
			$cursor->setTypeMap(['document' => 'array']);
			$result = current($cursor->toArray());
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		return isset($result->result) ? $result->result : [];
	}

	public function distinct($collection, $item, array $criteria = [])
	{
		try {
			$command = new MongoDB\Driver\Command([
				'distinct' => $collection,
				'key' => $item,
				'query' => (object) $criteria
			]);

			$result = current($this->driver->execute($command)->toArray());
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		return isset($result->values) ? (array) $result->values : [];
	}

	############################## write operations ###############################

	public function delete($collection, array $criteria, $multi = TRUE)
	{

		try {
			$writer = new MongoDB\Driver\BulkWrite();
			$writer->delete($criteria, ['limit' => $multi ? 0 : 1]);
			$result = $this->driver->execute($collection, $writer);
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		if ($count = $result->getDeletedCount()) {
			return $count;
		}

		return FALSE;
	}

	public function insert($collection, array $data)
	{

		try {
			$writer = new MongoDB\Driver\BulkWrite();
			$id = $writer->insert($data);
			$result = $this->driver->execute($collection, $writer);
		} catch (RuntimeException $e) {
			$this->createException($e);
		}
		if ($result->getInsertedCount()) {
			return array_merge(['_id' => $id], $data);
		}

		return FALSE;
	}

	public function update($collection, array $data, array $criteria, $upsert = FALSE, $multi = TRUE)
	{
		try {
			$writer = new MongoDB\Driver\BulkWrite();
			$writer->update($criteria, $data, ['multi' => (bool) $multi, 'upsert' => (bool) $upsert]);
			$result = $this->driver->execute($collection, $writer);
		} catch (RuntimeException $e) {
			$this->createException($e);
		}

		if ($upsert && $result->getUpsertedCount()) {
			foreach ($result->getUpsertedIds() as $oid) {
				return ['_id' => $oid];
			}
		}

		if ($count = $result->getModifiedCount()) {
			return (int) $count;
		}

		return FALSE;
	}

	private function createException(RuntimeException $e)
	{
		throw new QueryException($e->getMessage(), $e->getCode(), $e);
	}

}
