<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use MongoCollection,
	Mva\Dbm\Query\IQueryAdapter,
	Mva\Dbm\QueryException;

class MongoQueryAdapter implements IQueryAdapter
{

	/** @var MongoDriver */
	private $driver;

	/** @var MongodbWriteBatch */
	private $writeBatch;

	public function __construct(MongoDriver $driver)
	{
		$this->driver = $driver;
	}

	public function find($collection, $fields = [], array $criteria = [], array $options = [])
	{
		try {
			$result = $this->getCollection($collection)->find($criteria, $fields);

			if (isset($options[self::LIMIT])) {
				$result->limit($options[self::LIMIT]);
			}

			if (isset($options[self::OFFSET])) {
				$result->skip($options[self::OFFSET]);
			}

			if (isset($options[self::ORDER])) {
				$result->sort($options[self::ORDER]);
			}
		} catch (\MongoException $e) {
			$this->createException($e);
		}

		return $result;
	}

	public function count($collection, array $criteria = [], array $options = [])
	{
		try {
			return $this->getCollection($collection)->count($criteria, $options);
		} catch (\MongoException $e) {
			$this->createException($e);
		}
	}

	public function aggregate($collection, $pipelines)
	{
		try {
			return $this->getCollection($collection)->aggregateCursor($pipelines);
		} catch (\MongoException $e) {
			$this->createException($e);
		}
	}

	public function distinct($collection, $item, array $criteria = [])
	{
		try {
			return (array) $this->getCollection($collection)->distinct($item, empty($criteria) ? NULL : $criteria);
		} catch (\MongoException $e) {
			$this->createException($e);
		}
	}

	public function delete($collection, array $criteria, $multi = TRUE)
	{
		$options = $this->withWriteOptions(['justOne' => !$multi]);

		try {
			$return = $this->getCollection($collection)->remove($criteria, $options);
		} catch (\MongoException $e) {
			$this->createException($e);
		}

		return $return['n'];
	}

	public function insert($collection, array $data)
	{
		try {
			$this->getCollection($collection)->insert($data, $this->withWriteOptions());
		} catch (\MongoException $e) {
			$this->createException($e);
		}

		return $data;
	}

	public function update($collection, array $data, array $criteria, $upsert = FALSE, $multi = TRUE)
	{
		$options = $this->withWriteOptions(['upsert' => (bool) $upsert, 'multiple' => (bool) $multi]);

		try {
			$result = $this->getCollection($collection)->update($criteria, $data, $options);
		} catch (\MongoException $e) {
			$this->createException($e);
		}

		if (isset($result['upserted'])) {
			return ['_id' => $result['upserted']];
		}

		return $result['n'];
	}

	/** @return MongoCollection */
	protected function getCollection($name)
	{
		return $this->driver->getResource()->selectCollection($name);
	}

	protected function withWriteOptions(array $options = [])
	{
		return array_merge(['w' => 1, 'wTimeoutMS' => 10000, 'j' => FALSE], $options);
	}

	private function createException(\MongoException $e)
	{
		throw new QueryException($e->getCode(), $e->getMessage(), $e);
	}

}
