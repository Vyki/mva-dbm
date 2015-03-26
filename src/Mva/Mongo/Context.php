<?php

namespace Mva\Mongo;

use Mva,
	Nette,
	MongoClient,
	MongoUpdateBatch,
	MongoInsertBatch;

/**
 * MongoDB context.
 *
 * @author Roman Vykuka
 */
class Context extends Nette\Object
{

	/** @var \MongoDB */
	private $database;

	public function __construct($name, MongoClient $connection)
	{
		$this->database = $connection->selectDB($name);
	}

	/**
	 * Returns collection
	 * @param string Collection name
	 * @return Collection
	 */
	public function collection($name)
	{
		return new Mva\Mongo\Collection($name, $this->database);
	}

	/** @return MongoUpdateBatch */
	public function batchUpdate($name)
	{
		if (class_exists('\\MongoUpdateBatch')) {
			return new MongoUpdateBatch($this->database->selectCollection($name));
		}

		throw new NotSupportedException('Batch update is not available in your version of the PHP Mongo extension. Update it to version 1.5.0 or newer.');
	}

	/** @return MongoInsertBatch */
	public function batchInsert($name)
	{
		if (class_exists('\\MongoInsertBatch')) {
			return new MongoInsertBatch($this->database->selectCollection($name));
		}

		throw new NotSupportedException('Batch insert is not available in your version of the PHP Mongo extension. Update it to version 1.5.0 or newer.');
	}

}
