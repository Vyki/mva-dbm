<?php

namespace Dbm\Tests;

use Tester\Assert,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Result\ResultWriteBatch as Result;

require __DIR__ . "/../../bootstrap.php";

class ResultWriteBatchTest extends UnitTestCase
{

	/** @var Result */
	private $result;

	protected function setUp()
	{
		parent::setUp();

		$this->result = new Result([
			Result::UPDATED => 1,
			Result::INSERTED => 2,
			Result::MATCHED => 3,
			Result::UPSERTED => 4,
			Result::DELETED => 5,
			Result::INSERTED_IDS => ['1abg323cd', '2abg323cd'],
			Result::UPSERTED_IDS => ['3abg323cd', '4abg323cd']
		]);
	}

	function testGetters()
	{
		$values = [
			$this->result->getUpdatedCount() => 1,
			$this->result->getInsertedCount() => 2,
			$this->result->getMatchedCount() => 3,
			$this->result->getUpsertedCount() => 4,
			$this->result->getDeletedCount() => 5
		];

		Assert::same(array_values($values), array_keys($values));
	}

	function testGetStats()
	{
		Assert::same([
			Result::UPDATED => 1,
			Result::INSERTED => 2,
			Result::MATCHED => 3,
			Result::UPSERTED => 4,
			Result::DELETED => 5], $this->result->getStats());
	}

	function testGetInsertedIds()
	{
		Assert::same(['1abg323cd', '2abg323cd'], $this->result->getInsertedIds());
	}

	function testLastInsertedId()
	{
		Assert::same('2abg323cd', $this->result->getLastInsertedId());
	}

	function testGetUpsertedIds()
	{
		Assert::same(['3abg323cd', '4abg323cd'], $this->result->getUpsertedIds());
	}

	function testLastUpsertedId()
	{
		Assert::same('4abg323cd', $this->result->getLastUpsertedId());
	}

}

$test = new ResultWriteBatchTest();
$test->run();
