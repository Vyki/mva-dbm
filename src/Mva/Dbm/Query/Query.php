<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query;

use Mva,
	Mva\Dbm\Query\IQuery,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResult,
	Mva\Dbm\Query\QueryProcessor,
	Mva\Dbm\Result\ResultFactory,
	Mva\Dbm\Result\IResultFactory,
	Mva\Dbm\Result\ResultWriteBatch as Result;

class Query implements IQuery
{

	const COUNT = 'count';
	const DISTINCT = 'distinct';
	const UPSERT = 'upsert';
	const MULTIPLE = 'multi';

	/** @var callable[] */
	public $onQuery = [];

	/** @var IDriver */
	protected $driver;

	/** @var IQuery */
	protected $queryAdapter;

	/** @var MongoQueryProcessor */
	protected $preprocessor;

	/** @var IResultFactory */
	protected $resultFactory;

	public function __construct(IDriver $driver, QueryProcessor $preprocessor)
	{
		$this->driver = $driver;
		$this->queryAdapter = $driver->getQueryAdapter();
		$this->preprocessor = $preprocessor;
	}

	############################## select operations ###############################

	/**
	 * @param string
	 * @param array|string ['id' => TRUE, 'name' => FALSE, ...] | ['id', '!name', ...] | $pipeline[[...], [...]] | [IQuery::DISTINCT => 'column'] | IQuery::COUNT
	 * @param array
	 * @param array [IQuery::LIMIT => 10, IQuery::SKIP => 3, ...]	
	 * @return IResult
	 */
	public function find($collection, $fields = [], array $criteria = [], array $options = [])
	{
		//identifies aggaregation pipelines
		if (isset($fields[0]) && is_array($fields[0])) {
			return $this->aggregate($collection, $fields);
		}
		//translates criteria
		if (!empty($criteria)) {
			$criteria = $this->preprocessor->processCondition($criteria);
		}
		//identifies distinct
		if (isset($fields[self::DISTINCT]) && is_string($fields[self::DISTINCT])) {
			return $this->distinct($collection, $fields[self::DISTINCT], $criteria);
		}
		//identifies count
		if ($fields === self::COUNT) {
			return $this->count($collection, $criteria, $options);
		}

		//process order
		if (isset($options[self::ORDER])) {
			$options[self::ORDER] = $this->preprocessor->processOrder((array) $options[self::ORDER]);
		}

		$select = $this->preprocessor->processSelect((array) $fields);

		$result = $this->queryAdapter->find($collection, $select, $criteria, $options);

		$return = $this->createResult($result);

		$this->onQuery($collection, 'find', ['fields' => $select, 'criteria' => $criteria, 'options' => $options], $return);

		return $return;
	}

	/**
	 * @return int
	 */
	public function count($collection, array $criteria = [], array $options = [])
	{
		$conds = $this->preprocessor->processCondition($criteria);

		$result = (int) $this->queryAdapter->count($collection, $conds, $options);

		$this->onQuery($collection, 'count', ['criteria' => $conds, 'options' => $options], $result);

		return $result;
	}

	/**
	 * @return IResult 
	 */
	public function aggregate($collection, $pipelines)
	{
		$order = $this->preprocessor->formatCmd('sort');
		$match = $this->preprocessor->formatCmd('match');

		foreach ($pipelines as &$pipeline) {
			if (isset($pipeline[$order])) {
				$pipeline[$order] = $this->preprocessor->processOrder($pipeline[$order]);
			}

			if (isset($pipeline[$match])) {
				$pipeline[$match] = $this->preprocessor->processCondition($pipeline[$match]);
			}
		}

		$result = $this->queryAdapter->aggregate($collection, $pipelines);

		$return = $this->createResult($result);

		$this->onQuery($collection, 'aggregate', $pipelines, $return);

		return $return;
	}

	/**
	 * @return IResult 
	 */
	public function distinct($collection, $item, array $criteria = [])
	{
		$conds = $this->preprocessor->processCondition($criteria);

		$result = $this->queryAdapter->distinct($collection, $item, $conds);

		foreach ($result as $key => $row) {
			$result[$key] = [$item => $row];
		}

		$return = $this->createResult($result);

		$this->onQuery($collection, 'distinct', ['fields' => $item, 'criteria' => $conds], $return);

		return $return;
	}

	############################## write operations ###############################

	public function delete($collection, array $criteria, $multi = TRUE)
	{
		$conds = $this->preprocessor->processCondition($criteria);

		$result = (int) $this->queryAdapter->delete($collection, $conds, $multi);

		$this->onQuery($collection, 'delete', ['criteria' => $conds, 'multi' => $multi], $result);

		return $result;
	}

	public function insert($collection, array $data)
	{
		$wdata = $this->preprocessor->processData($data, TRUE);

		$result = $this->queryAdapter->insert($collection, $wdata);

		$this->onQuery($collection, 'insert', ['data' => $result], 1);

		return $this->createResult([$result])->fetch();
	}

	public function update($collection, array $data, array $criteria, $upsert = FALSE, $multi = TRUE)
	{
		$wdata = $this->preprocessor->processUpdate($data);

		$conds = $this->preprocessor->processCondition($criteria);

		$result = $this->queryAdapter->update($collection, $wdata, $conds, $upsert, $multi);

		$logpar = ['data' => $wdata, 'criteria' => $conds, 'upsert' => $upsert, 'multi' => $multi];

		if ($upsert && is_array($result)) {
			if (count($wdata) === 1 && isset($wdata[$this->preprocessor->formatCmd('set')])) {
				$result = array_merge($result, $wdata[$this->preprocessor->formatCmd('set')]);
			}

			$return = $this->createResult([$result])->fetch();

			$this->onQuery($collection, 'upsert', $logpar, $result);

			return $return;
		}

		$this->onQuery($collection, 'update', $logpar, (int) $result);

		return (int) $result;
	}

	public function writeBatch($collection, QueryWriteBatch $batch)
	{
		$result = $this->driver->getWriteBatch()->write($collection, $batch);

		isset($result[Result::INSERTED_IDS]) && $result[Result::INSERTED_IDS] = array_map([$this->driver, 'convertToPhp'], $result[Result::INSERTED_IDS]);
		isset($result[Result::UPSERTED_IDS]) && $result[Result::UPSERTED_IDS] = array_map([$this->driver, 'convertToPhp'], $result[Result::UPSERTED_IDS]);

		$return = new Result($result);

		$this->onQuery($collection, 'write batch', [], $return);

		return $return;
	}

	/** @return IResultFactory */
	protected function getResultFactory()
	{
		if (!$this->resultFactory) {
			$this->resultFactory = new ResultFactory($this->driver);
		}

		return $this->resultFactory;
	}

	public function setResultfactory(IResultFactory $factory)
	{
		$this->resultFactory = $factory;
	}

	################################## internals ##################################

	/** @return Mva\Dbm\Result\IResult */
	protected function createResult($data)
	{
		return $this->getResultFactory()->create($data);
	}

	/** @return void */
	protected function onQuery($collection, $operation, $params, $result)
	{
		foreach ($this->onQuery as $callback) {
			call_user_func_array($callback, [$collection, $operation, $params, $result]);
		}
	}

}
