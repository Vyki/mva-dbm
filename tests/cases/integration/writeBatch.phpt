<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini
 */

namespace Dbm\Tests\Driver\Mongo;

use Tester\Assert,
	Dbm\Tests\DriverTestCase,
	Mva\Dbm\Query\QueryWriteBatch,
	Mva\Dbm\Result\ResultWriteBatch as Result;

$connection = include __DIR__ . "/../../bootstrap.php";

class WriteBatchTest extends DriverTestCase
{

	/** @var Mva\Dbm\Query\IWriteBatch */
	private $batch;

	/** @var QueryWriteBatch */
	private $queue;

	protected function setUp()
	{
		$this->loadData('test_batch');
		$this->batch = $this->getConnection()->getDriver()->getWriteBatch();
		$this->queue = $this->getConnection()->createWriteBatch();
	}

	public function testExecute()
	{
		$this->loadQueue();

		$result = $this->batch->write('test_batch', $this->queue);

		Assert::type('array', $result[Result::INSERTED_IDS]);
		Assert::count(1, $result[Result::INSERTED_IDS]);

		Assert::type('array', $result[Result::UPSERTED_IDS]);
		Assert::count(1, $result[Result::UPSERTED_IDS]);

		unset($result[Result::INSERTED_IDS]);
		unset($result[Result::UPSERTED_IDS]);

		$expected = [
			Result::UPDATED => 3,
			Result::INSERTED => 1,
			Result::MATCHED => 3,
			Result::UPSERTED => 1,
			Result::DELETED => 2
		];

		Assert::equal($expected, $result);
	}

	private function loadQueue()
	{
		$this->queue->insert([
			'pr_id' => 4,
			'name' => 'Test 8',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		]);

		$this->queue->update(['name' => 'Test 200', '$rename' => ['points' => 'coords']], ['pr_id' => 2]);

		$this->queue->update(['pr_id' => '2', 'name' => 'Test 7', 'domain' => 'gama'], ['domain' => 'gama'], TRUE);

		$this->queue->delete(['pr_id' => 1], TRUE);
	}

}

$test = new WriteBatchTest();
$test->run();
