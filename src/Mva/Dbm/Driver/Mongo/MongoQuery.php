<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Mva,
	Nette;

class MongoQuery extends Nette\Object implements Mva\Dbm\Driver\IQuery
{

	/** @var MongoDriver */
	private $driver;

	/** @var MongoQueryProcessor */
	private $preprocessor;

	/** @var callable[] */
	public $onQuery = [];

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
			return $this->selectCount($collection, $criteria, $options);
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

		$this->onQuery($collection, 'select', ['fields' => $select, 'criteria' => $criteria, 'options' => $options], ['matched' => $result->count()]);

		return new MongoResult($result);
	}

	public function selectCount($collection, array $criteria = [], array $options = [])
	{
		$result = $this->driver->getCollection($collection)->count($criteria, $options);
		$this->onQuery($collection, 'select - count', ['criteria' => $criteria, 'options' => $options], ['count' => $result]);

		return $result;
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
		$this->onQuery($collection, 'select - aggregate', ['pipelines' => $pipelines], ['matched' => iterator_count($result)]);

		return new MongoResult($result);
	}

	public function selectDistinct($collection, $item, array $criteria = [])
	{
		$result = (array) $this->driver->getCollection($collection)->distinct($item, empty($criteria) ? NULL : $criteria);

		foreach ($result as $key => $row) {
			$result[$key] = [$item => $row];
		}

		$this->onQuery($collection, 'select - distinct', ['fields' => $item, 'criteria' => $criteria], ['count' => count($result)]);

		return new MongoResult($result);
	}

	public function delete($collection, array $criteria, array $options = [])
	{
		$criteria = $criteria = $this->preprocessor->processCondition($criteria);
		$return = $this->driver->getCollection($collection)->remove($criteria, $options);
		$this->onQuery($collection, 'delete', ['criteria' => $criteria, 'options' => $options], ['deleted' => $return['n']]);

		return $return['n'];
	}

	public function insert($collection, array $data, array $options = [])
	{
		$data = $this->preprocessor->processData($data);
		$this->driver->getCollection($collection)->insert($data, $options);
		$result = new MongoResult([$data]);
		$data = $result->fetch();
		$this->onQuery($collection, 'insert', ['data' => $data, 'options' => $options], ['inserted' => 1]);

		return $data;
	}

	public function update($collection, array $data, array $criteria, array $options = [])
	{
		$data = $this->preprocessor->processUpdate($data);
		$criteria = $this->preprocessor->processCondition($criteria);
		$return = $this->driver->getCollection($collection)->update($criteria, $data, $options);

		if (isset($return['upserted'])) {
			$data = array_merge(['_id' => $return['upserted']], $data[$this->preprocessor->formatCmd('set')]);
			$op = ['upsert', 'upserted'];
		} else {
			$op = ['update', 'updated'];
		}

		$result = new MongoResult([$data]);
		$data = $result->fetch();

		$this->onQuery($collection, $op[0], ['data' => $data, 'criteria' => $criteria, 'options' => $options], [$op[1] => $return['n']]);

		return $op[0] === 'update' ? $return['n'] : $data;
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
