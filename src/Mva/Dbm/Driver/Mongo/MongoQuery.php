<?php

namespace Mva\Dbm\Driver\Mongo;

use Mva,
	Nette;

class MongoQuery extends Nette\Object implements Mva\Dbm\Driver\IQuery
{

	/** @var MongoDriver */
	private $driver;

	/** @var MongoQueryProcessor */
	private $preprocessor;

	public function __construct(MongoDriver $driver)
	{
		$this->driver = $driver;
		$this->preprocessor = $driver->preprocessor;
	}

	/**
	 * @return MongoResult 
	 * @param string
	 * @param array|string ['id' => TRUE, 'name' => FALSE, ...] | ['id', '!name', ...] | $pipeline[[...], [...]] | ['distinct' => 'column'] | 'count'
	 * @param array
	 * @param array ['limit' => 10, 'skip' => 3, ...]	 
	 */
	public function select($collection, $fields = [], array $criteria = [], array $options = [])
	{
		//identifies aggaregation pipelines
		if (isset($fields[0]) && is_array($fields[0])) {
			return $this->selectAggregate($collection, $fields);
		}
		//translates criteria
		if (!empty($criteria)) {
			$criteria = $this->preprocessor->processCondition($criteria);
		}
		//identifies distinct
		if (isset($fields[self::SELECT_DISTINCT]) && is_string($fields[self::SELECT_DISTINCT])) {
			return $this->selectDistinct($collection, (string) $fields[self::SELECT_DISTINCT], $criteria);
		}
		
		if ($fields === self::SELECT_COUNT) {
			return $this->driver->getCollection($collection)->count($criteria, $options);
		}
		
		$select = $this->preprocessor->processSelect((array) $fields);

		$result = $this->driver->getCollection($collection)->find($criteria, $select);

		if (isset($options[self::SELECT_LIMIT])) {
			$result->limit($options[self::SELECT_LIMIT]);
		}

		if (isset($options[self::SELECT_OFFSET])) {
			$result->skip($options[self::SELECT_OFFSET]);
		}

		if (isset($options[self::SELECT_ORDER])) {
			$result->sort($options[self::SELECT_ORDER]);
		}

		return new MongoResult($result);
	}

	public function selectAggregate($collection, $pipelines)
	{
		$match = $this->preprocessor->formatCmd('match');

		foreach ($pipelines as &$pipeline) {
			if (isset($pipeline[$match])) {
				$pipeline[$match] = $this->preprocessor->processCondition($pipeline[$match]);
			}
		}

		$result = $this->driver->getCollection($collection)->aggregateCursor($pipelines);

		return new MongoResult($result);
	}

	public function selectDistinct($collection, $item, array $criteria = [])
	{
		$result = (array) $this->driver->getCollection($collection)->distinct($item, empty($criteria) ? NULL : $criteria);

		foreach ($result as $key => $row) {
			$result[$key] = [$item => $row];
		}

		return new MongoResult($result);
	}

	public function delete($collection, array $criteria, array $options = [])
	{
		return $this->driver->getCollection($collection)->remove($criteria, $options);
	}

	public function insert($collection, array $data, array $options = [])
	{
		return $this->driver->getCollection($collection)->insert($data, $options);
	}

	public function update($collection, array $data, array $criteria, array $options = [])
	{
		$parsedData = $this->preprocessor->processUpdate($data);
		$parsedCriteria = $this->preprocessor->processCondition($criteria);

		return $this->driver->getCollection($collection)->update($parsedCriteria, $parsedData, $options);
	}

	public function insertBatch($collection)
	{
		return new Batch\InsertBatch($this->driver, $collection);
	}

	public function updateBatch($collection)
	{
		return new Batch\UpdateBatch($this->driver, $collection);
	}

	public function deleteBatch($collection)
	{
		return new Batch\DeleteBatch($this->driver, $collection);
	}

}
