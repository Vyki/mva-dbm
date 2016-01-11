<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Mva\Dbm\Query\IWriteBatch,
	Mva\Dbm\Query\QueryWriteBatch as Batch,
	Mva\Dbm\Result\ResultWriteBatch as Result;

class MongoWriteBatch implements IWriteBatch
{

	const UPDATE = 'u';
	const CRITERIA = 'q';
	const DATA = 'data';

	/** @var MongoDriver */
	protected $driver;

	public function __construct(MongoDriver $driver)
	{
		$this->driver = $driver;
	}

	public function write($collection, Batch $batch)
	{
		$results = $upserted = $inserted = $errors = [];

		foreach ([Batch::INSERT, Batch::UPDATE, Batch::DELETE] as $op) {
			$queue = $batch->getQueue($op);

			if (empty($queue)) {
				continue;
			}

			$class = "\\Mongo{$op}Batch";

			$writer = new $class($this->driver->getResource()->selectCollection($collection));

			foreach ($queue as $item) {
				if ($op === Batch::INSERT) {
					!isset($item['_id']) && $item['_id'] = new \MongoId();
					$inserted[] = $item['_id'];
					$writer->add($item);
				}

				if ($op === Batch::UPDATE) {
					$writer->add([
						self::CRITERIA => $item[0],
						self::UPDATE => $item[1],
						Batch::UPSERT => $item[2][Batch::UPSERT],
						Batch::MULTIPLE => $item[2][Batch::MULTIPLE]
					]);
				}

				if ($op === Batch::DELETE) {
					$writer->add([
						self::CRITERIA => $item[0],
						Batch::LIMIT => $item[1][Batch::LIMIT],
					]);
				}
			}

			$result = $writer->execute(['w' => 1]);

			if (isset($result['ok'])) {
				$results = array_merge($results, $result);
			} else {
				$errors[] = $result;
			}
		}

		if (isset($results['upserted'])) {
			$upserted = array_map(function($item) {
				return $item['_id'];
			}, $results['upserted']);
		}

		$stats = $this->loadStats($results);
		$stats[Result::INSERTED_IDS] = $inserted;
		$stats[Result::UPSERTED_IDS] = $upserted;

		return $stats;
	}

	private function loadStats($result)
	{
		$return = [];

		$stats = [Result::UPDATED, Result::DELETED, Result::INSERTED, Result::UPSERTED, Result::MATCHED];

		foreach ($stats as $key) {
			$ukey = 'n' . ucfirst($key);
			$return[$key] = isset($result[$ukey]) ? $result[$ukey] : 0;
		}

		return $return;
	}

}
