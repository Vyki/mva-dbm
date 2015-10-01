<?php

namespace Dbm\Tests\Mongo;

use Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Driver\Mongo\Batch;

$connection = require __DIR__ . "/../../bootstrap.php";

class WriteBatchTest extends TestCase
{

	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_batch < " . __DIR__ . "/../test.json");
	}

	function testInsertBatch()
	{
		$batch = new Batch\InsertBatch($this->connection->driver, 'test_batch');
		 
		$batch->add($data1 = [
			'pr_id' => 4,
			'name' => 'Test 8',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		]);

		$batch->add($data2 = [
			'pr_id' => 4,
			'name' => 'Test 10',
			'domain' => 'beta',
			'size' => 104,
			'points' => [18, 31, 64],
			'type' => 10
		]);
		
		Assert::same([$data1, $data2], $batch->getQueue());
		
		$result = $batch->execute();

		Assert::same($result, [
			"nInserted" => 2,
			"ok" => TRUE
		]);
		
		Assert::same([], $batch->getQueue());
	}

	function testUpdateBatch()
	{
		$batch = new Batch\UpdateBatch($this->connection->driver, 'test_batch');

		$batch->add(['name' => 'Test 200', '$rename' => ['points' => 'coords']])->where(['pr_id' => 2]);

		$batch->add(['domain' => 'Test 400', '$unset' => ['type']])->where(['domain' => 'beta']);
		
		$queue = $batch->getQueue();
		
		Assert::same([
			'upsert' => FALSE,
			'multi' => TRUE,
			'u' => ['$rename' => ['points' => 'coords'], '$set'=> ['name' => 'Test 200']],
			'q' => ['pr_id' => 2],
		], $queue[0]);
      
		Assert::same([
			'upsert' => FALSE,
			'multi' => TRUE,
			'u' => ['$unset' => ['type' => ''], '$set'=> ['domain' => 'Test 400']],
			'q' => ['domain' => 'beta'],
		], $queue[1]);
				
		$result = $batch->execute();
		
		Assert::same($result, [
			'nMatched' => 5, 
			'nModified' => 5, 
			'nUpserted' => 0, 
			'ok' => TRUE]
		);
	}
	
	function testDeleteBatch()
	{
		$batch = new Batch\DeleteBatch($this->connection->driver, 'test_batch');
		
		$batch->add(['pr_id' => 2], 1);
		$batch->add(['domain' => 'beta']);

		$queue = $batch->getQueue();
		
		Assert::same([
			'limit' => 1,
			'q' => ['pr_id' => 2]
		], $queue[0]);
		
		Assert::same([
			'limit' => 1,
			'q' => ['domain' => 'beta']
		], $queue[1]);
		
		$result = $batch->execute();
		
		Assert::same($result, [
			'nRemoved' => 2, 
			'ok' => TRUE]
		);
	}
}

$test = new WriteBatchTest($connection);
$test->run();






