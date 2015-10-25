<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use DateTime,
	Mva\Dbm\Helpers,
	IteratorAggregate,
	Mva\Dbm\Result\IResult;

class MongoResult implements IteratorAggregate, IResult
{

	/** @var array|\Traversable */
	private $result;

	/** @var \Generator */
	private $resultGenerator = NULL;

	/** @var array */
	private $resultNormalized = [];

	public function __construct($result)
	{
		$this->result = $result;
		$this->resultGenerator = $this->createResultGenerator();
	}

	public function getResult()
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

	public function normalizeDocument($document)
	{
		$this->normalizeTree($document);
		return $document;
	}

	##################  internal normalization ##################

	private function normalizeTree(array &$document, $level = 0)
	{
		if ($level === 0 && isset($document['_id']) && is_array($document['_id'])) {
			$document = array_merge($document, $document['_id']);
			unset($document['_id']);
		}

		foreach ($document as &$item) {
			if (is_array($item)) {
				$this->normalizeTree($item, ++$level);
			} elseif ($item instanceof \MongoDate || $item instanceof \MongoTimestamp) {
				$item = new DateTime('@' . (string) $item->sec);
			} elseif ($item instanceof \MongoId) {
				$item = (string) $item;
			}
		}
	}

	##################  interface Iterator ##################

	public function getIterator()
	{
		return $this->createResultGenerator();
	}

	##################  result generator  ##################

	private function createResultGenerator()
	{
		foreach ($this->result as $key => $document) {

			if (!array_key_exists($key, $this->resultNormalized)) {
				$this->normalizeTree($document);
				$this->resultNormalized[$key] = $document;
			}

			yield $key => $this->resultNormalized[$key];
		}
	}

}
