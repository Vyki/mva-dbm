<?php

namespace Dbm\Tests\Mongo;

use Mva,
	Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Driver\Mongo\MongoWriteBatch;

$connection = include __DIR__ . "/../../bootstrap.php";

class MongoWriteBatchTest extends TestCase
{

	/** @var Mva\Dbm\Connection */
	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_batch < " . __DIR__ . "/../test.json");
	}

	public function getBatch()
	{
		return new MongoWriteBatch($this->connection->driver, 'test_batch');
	}

	public function testInsert()
	{
		$batch = $this->getBatch();

		$batch->insert($data1 = [
			'pr_id' => 4,
			'name' => 'Test 8',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		]);

		$batch->insert($data2 = [
			'pr_id' => 4,
			'name' => 'Test 10',
			'domain' => 'beta',
			'size' => 104,
			'points' => [18, 31, 64],
			'type' => 10
		]);

		$batch->insert([
			'pr_id%i' => '4',
			'name%s' => 'Test 8',
			'domain%s' => 'beta',
			'size%i' => '101',
			'points%i[]' => ['18', 31, 64.01],
			'type%i' => 10.1
		]);

		Assert::same([$data1, $data2, $data1], $batch->getQueue('insert'));
	}

	public function testUpdate()
	{
		$batch = $this->getBatch();

		$batch->update(['name%s' => 'Test 200', '$rename' => ['points' => 'coords']], ['pr_id > %i' => '2']);

		$batch->update(['domain%s' => 'Test 400', '$unset' => ['type']], ['domain' => 'beta'], TRUE, FALSE);

		$exp1 = [
			'q' => ['pr_id' => ['$gt' => 2]],
			'u' => ['$rename' => ['points' => 'coords'], '$set' => ['name' => 'Test 200']],
			'upsert' => FALSE,
			'multi' => TRUE,
		];

		$exp2 = [
			'q' => ['domain' => 'beta'],
			'u' => ['$unset' => ['type' => ''], '$set' => ['domain' => 'Test 400']],
			'upsert' => TRUE,
			'multi' => FALSE,
		];

		Assert::same([$exp1, $exp2], $batch->getQueue('update'));
	}

	public function testDelete()
	{
		$batch = $this->getBatch();

		$batch->delete(['pr_id > %i' => 2, 'domain = %s' => 'beta'], TRUE);
		$batch->delete(['pr_id <= %i' => 10, 'domain' => ['beta', 'alpha']], FALSE);

		$exp1 = [
			'q' => ['$and' => [['pr_id' => ['$gt' => 2]], ['domain' => 'beta']]],
			'limit' => 1,
		];

		$exp2 = [
			'q' => ['$and' => [['pr_id' => ['$lte' => 10]], ['domain' => ['$in' => ['beta', 'alpha']]]]],
			'limit' => 0
		];

		Assert::same([$exp1, $exp2], $batch->getQueue('delete'));
	}

	public function testExecute()
	{
		$log = [];

		$this->connection->query->onQuery[] = function ($coll, $oper, $param, $res) use (&$log) {
			$log[] = [$coll, $oper, $param, $res];
		};

		$batch = $this->getBatch();

		$batch->insert($data1 = [
			'pr_id' => 4,
			'name' => 'Test 8',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		]);

		$batch->update(['name' => 'Test 200', '$rename' => ['points' => 'coords']], ['pr_id' => 2]);

		$batch->delete(['pr_id' => 1], FALSE);

		$result = $batch->execute();

		Assert::same([
			"inserted" => 1,
			"matched" => 3,
			"modified" => 3,
			"upserted" => 0,
			"removed" => 2], $result);

		Assert::same($this->getExpectedLog(), $log);
	}

	public function testReset()
	{
		$batch = $this->getBatch();

		$batch->insert(['pr_id' => 4, 'name' => 'Test 8', 'domain' => 'beta']);

		$batch->update(['name' => 'Test 200', '$rename' => ['points' => 'coords']], ['pr_id' => 2]);

		$batch->delete(['pr_id' => 1], FALSE);

		$queue = $batch->getQueue();

		Assert::count(1, $queue['insert']);
		Assert::count(1, $queue['update']);
		Assert::count(1, $queue['delete']);

		$batch->reset();

		Assert::same(['insert' => [], 'update' => [], 'delete' => []], $batch->getQueue());
	}

	public function testGetUpserted()
	{
		$batch = $this->getBatch();

		$batch->update(['pr_id' => 4, 'name' => 'Test 8', 'domain' => 'beta'], ['type' => 20], TRUE);

		$result = $batch->execute();

		Assert::same(['matched' => 0, 'modified' => 0, 'upserted' => 1], $result);

		$upserted = $batch->getUpserted();

		Assert::type('array', $upserted);
		Assert::count(1, $upserted);
		Assert::type('string', reset($upserted));
		Assert::type('int', key($upserted));
	}

	######################### data provider #########################3

	public function getExpectedLog()
	{
		return [
			[
				'test_batch',
				'insert - batch',
				['w' => 1],
				['inserted' => 1],
			],
			[
				'test_batch',
				'update - batch',
				['w' => 1],
				['matched' => 3, 'modified' => 3, 'upserted' => 0],
			],
			[
				'test_batch',
				'delete - batch',
				['w' => 1],
				['removed' => 2],
			]
		];
	}

}

$test = new MongoWriteBatchTest($connection);
$test->run();
