<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Result;

class ResultWriteBatch
{

	const UPDATED = 'modified';
	const MATCHED = 'matched';
	const DELETED = 'removed';
	const UPSERTED = 'upserted';
	const INSERTED = 'inserted';
	const INSERTED_IDS = 'insertedIds';
	const UPSERTED_IDS = 'upsertedIds';

	/** @var int */
	private $modified = 0;

	/** @var int */
	private $removed = 0;

	/** @var int */
	private $matched = 0;

	/** @var int */
	private $upserted = 0;

	/** @var int */
	private $inserted = 0;

	/** @var array */
	private $insertedIds = [];

	/** @var array */
	private $upsertedIds = [];

	public function __construct(array $stats, array $insIds = [], array $upsIds = [])
	{
		$this->upsertedIds = $upsIds;
		$this->insertedIds = $insIds;
		
		foreach ($stats as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}

	public function getStats()
	{
		return [
			self::UPDATED => $this->modified,
			self::INSERTED => $this->inserted,
			self::MATCHED => $this->matched,
			self::UPSERTED => $this->upserted,
			self::DELETED => $this->removed
		];
	}

	public function getUpdatedCount()
	{
		return $this->modified;
	}

	public function getInsertedCount()
	{
		return $this->inserted;
	}

	public function getMatchedCount()
	{
		return $this->matched;
	}

	public function getDeletedCount()
	{
		return $this->removed;
	}

	public function getUpsertedCount()
	{
		return $this->upserted;
	}

	public function getUpsertedIds()
	{
		return $this->upsertedIds;
	}

	public function getInsertedIds()
	{
		return $this->insertedIds;
	}

	public function getLastInsertedId()
	{
		return end($this->insertedIds);
	}

	public function getLastUpsertedId()
	{
		return end($this->upsertedIds);
	}

}
