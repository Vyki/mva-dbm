<?php

namespace Dbm\Tests\Mongo;

use Mva,
	Tester\Assert,
	Tester\TestCase;

$connection = require __DIR__ . "/../../bootstrap.php";

class MongoQueryTest extends TestCase
{

	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_query < " . __DIR__ . "/../test.json");
	}

	/** @return Mva\Dbm\Driver\Mongo\MongoQuery */
	function getQuery()
	{
		return $this->connection->query;
	}

	function testSelect()
	{
		$query = $this->getQuery();

		$result = $query->select('test_query', ['!_id', 'name', 'domain'], ['domain = beta']);

		$data = $result->fetchAll();

		Assert::same([['name' => 'Test 5', 'domain' => 'beta'], ['name' => 'Test 6', 'domain' => 'beta']], $data);
	}

	function testInsert()
	{
		$query = $this->getQuery();

		$insert = [
			'pr_id' => 2,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $query->insert('test_query', $insert);
		
		Assert::truthy($ret);

		$result = $query->select('test_query', ['!_id'], ['_id = %oid' => $insert['_id']])->fetch();

		unset($insert['_id']);

		Assert::same($insert, $result);
	}

	function testUpdate()
	{
		$query = $this->getQuery();

		$condition = ['_id = %oid' => '54ccf5639ab253f598d6b4a5'];

		$ret = $query->update('test_query', ['domain' => 'theta'], $condition);

		Assert::truthy($ret);

		$result = $query->select('test_query', ['domain'], $condition)->fetch();

		Assert::same('theta', $result['domain']);
	}

	function testUpdateManipulation()
	{
		$query = $this->getQuery();

		$condition = ['_id = %oid' => '54ccf3509ab253f598d6b4a0'];

		$data = [
			'size' => 40,
			'$set' => ['name' => 'test update'],
			'$unset' => ['domain'],
			'$rename' => ['type' => 'category']
		];

		$ret = $query->update('test_query', $data, $condition);

		Assert::truthy($ret);

		$result = $query->select('test_query', ['!_id'], $condition)->fetch();

		Assert::same('test update', $result['name']);
		Assert::false(array_key_exists('domain', $result));
		Assert::false(array_key_exists('type', $result));
		Assert::true(array_key_exists('category', $result));
	}

}

$test = new MongoQueryTest($connection);
$test->run();






