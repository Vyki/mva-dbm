<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Result;

use Mva\Dbm\Helpers,
	IteratorAggregate,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\IResult;

class Result implements IteratorAggregate, IResult
{

	/** @var IDriver */
	private $driver;

	/** @var array|\Traversable */
	private $result;

	/** @var \Generator */
	private $resultGenerator;

	public function __construct(IDriver $driver, $result)
	{
		$this->driver = $driver;
		$this->result = $result;
		$this->resultGenerator = $this->createResultGenerator();
	}

	public function getRawResult()
	{
		return $this->result;
	}

	public function fetch()
	{
		$ret = $this->resultGenerator->current();
		$this->resultGenerator->next();
		return $ret;
	}

	public function fetchAll()
	{
		return iterator_to_array($this);
	}

	public function fetchField()
	{
		if ($row = $this->fetch()) {
			foreach ($row as $value) {
				return $value;
			}
		}

		return NULL;
	}

	public function fetchPairs($key = NULL, $value = NULL)
	{
		return Helpers::fetchPairs($this, $key, $value);
	}

	public function normalizeDocument($data)
	{
		$this->normalizeTree($data);
		return $data;
	}

	################## internal normalization ##################

	private function normalizeTree(array &$data, $level = 0)
	{
		if ($level === 0 && isset($data['_id']) && is_array($data['_id'])) {
			$data = array_merge($data['_id'], $data);
			unset($data['_id']);
		}

		foreach ($data as &$item) {
			if (is_array($item)) {
				$this->normalizeTree($item, ++$level);
			} elseif (is_object($item)) {
				$item = $this->driver->convertToPhp($item);
			}
		}
	}

	##################  interface Iterator ##################

	/**
	 * @return \Generator	 
	 */
	public function getIterator()
	{
		return $this->createResultGenerator();
	}

	##################  result generator  ##################

	/**
	 * @return \Generator	 
	 */
	private function createResultGenerator()
	{
		foreach ($this->result as $data) {
			yield $this->normalizeDocument($data);
		}
	}

}
