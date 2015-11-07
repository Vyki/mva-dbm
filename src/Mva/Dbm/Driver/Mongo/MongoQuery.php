<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Mva,
	Nette,
	Mva\Dbm\Query\IQuery;

class MongoQuery extends Nette\Object implements IQuery
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

		return $this->createResult($result);
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
		$this->onQuery($collection, 'select - aggregate', $pipelines, ['count' => iterator_count($result)]);

		return $this->createResult($result);
	}

	public function selectDistinct($collection, $item, array $criteria = [])
	{
		$result = (array) $this->driver->getCollection($collection)->distinct($item, empty($criteria) ? NULL : $criteria);

		foreach ($result as $key => $row) {
			$result[$key] = [$item => $row];
		}

		$this->onQuery($collection, 'select - distinct', ['fields' => $item, 'criteria' => $criteria], ['count' => count($result)]);

		return $this->createResult($result);
	}

	public function delete($collection, array $criteria, $options = [])
	{
		if (is_bool($options)) {
			$options = [self::DELETE_ONE => $options];
		}

		$criteria = $criteria = $this->preprocessor->processCondition($criteria);
		$return = $this->driver->getCollection($collection)->remove($criteria, $options);
		$this->onQuery($collection, 'delete', ['criteria' => $criteria, 'options' => $options], ['deleted' => $return['n']]);

		return $return['n'];
	}

	public function insert($collection, array $data, $options = [])
	{
		$data = $this->preprocessor->processData($data, TRUE);
		$this->driver->getCollection($collection)->insert($data, $options);
		$data = $this->createResult([$data])->fetch();
		$this->onQuery($collection, 'insert', ['data' => $data, 'options' => $options], ['inserted' => 1]);

		return $data;
	}

	public function update($collection, array $data, array $criteria, $options = [], $multi = TRUE)
	{
		$data = $this->preprocessor->processUpdate($data);
		$options = $this->processUpdateOptions($options, $multi);
		$criteria = $this->preprocessor->processCondition($criteria);

		$result = $this->driver->getCollection($collection)->update($criteria, $data, $options);
		list($op, $chname, $data) = $this->processUpdateResult($result, $data);
		$this->onQuery($collection, $op, ['data' => $data, 'criteria' => $criteria, 'options' => $options], [$chname => $result['n']]);

		return $op === 'update' ? $result['n'] : $data;
	}

	public function batch($collection)
	{
		return new MongoWriteBatch($this->driver, $collection);
	}

	################################## internals ##################################

	/** @return Mva\Dbm\Result\IResult */
	protected function createResult($data)
	{
		return $this->driver->resultFactory->create($data);
	}

	private function processUpdateOptions($upsert, $multi)
	{
		$opt = is_array($upsert) ? $upsert : [];

		if (!isset($opt[self::UPDATE_UPSERT])) {
			$opt[self::UPDATE_UPSERT] = is_bool($upsert) ? $upsert : FALSE;
		}

		if (!isset($opt[self::UPDATE_MULTIPLE])) {
			$opt[self::UPDATE_MULTIPLE] = (bool) $multi;
		}

		return $opt;
	}

	private function processUpdateResult($result, $data)
	{
		if (isset($result['upserted'])) {
			$data = array_merge(['_id' => $result['upserted']], $data[$this->preprocessor->formatCmd('set')]);
			$return = ['upsert', 'upserted'];
		} else {
			$return = ['update', 'modified'];
		}

		$return[] = $this->createResult([$data])->fetch();

		return $return;
	}

}
