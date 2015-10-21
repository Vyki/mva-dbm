<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use DateTime,
	IteratorAggregate,
	Mva\Dbm\InvalidArgumentException;

class MongoResult implements IteratorAggregate
{

	/** @var \Traversable|array */
	private $result;

	/** @var \Generator */
	private $resultGenerator = NULL;

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
		$return = [];

		if ($key === NULL && $value === NULL) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value.');
		}

		if ($key === NULL) {
			foreach ($this as $row) {
				$return[] = $row[$value];
			}
		} elseif ($value === NULL) {
			foreach ($this as $row) {
				$return[is_object($row[$key]) ? (string) $row[$key] : $row[$key]] = $row;
			}
		} else {
			foreach ($this as $row) {
				$return[is_object($row[$key]) ? (string) $row[$key] : $row[$key]] = $row[$value];
			}
		}

		return $return;
	}

	public function normalizeDocument(array $document)
	{
		if (isset($document['_id']) && is_array($document['_id'])) {
			$document = array_merge($document, $document['_id']);
			unset($document['_id']);
		}

		$return = [];

		foreach ($document as $index => $item) {

			if (is_object($item) === FALSE) {
				$return[$index] = $item;
				continue;
			}

			switch (get_class($item)) {
				case 'MongoDate':
				case 'MongoTimestamp':
					$return[$index] = new DateTime('@' . (string) $item->sec);
					break;

				case 'MongoId':
					$return[$index] = (string) $item;
					break;

				default:
					$return[$index] = $item;
			}
		}

		return $return;
	}

	##################  interface Iterator ##################

	public function getIterator()
	{
		return $this->createResultGenerator();
	}

	##################  result generator  ##################

	private function createResultGenerator()
	{
		foreach ($this->result as $key => $row) {
			yield $key => $this->normalizeDocument($row);
		}
	}

}
