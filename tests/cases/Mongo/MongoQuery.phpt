<?php

namespace Dbm\Tests\Mongo;

use Mva,
	Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Driver\IQuery,
	Mva\Dbm\Driver\Mongo\MongoQueryBuilder;

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

	function testDistinct()
	{
		$query = $this->getQuery();

		$result = $query->select('test_query', [IQuery::SELECT_DISTINCT => 'domain']);

		$data = $result->fetchAll();

		Assert::same([['domain' => 'alpha'], ['domain' => 'beta']], $data);
	}

	function testCount()
	{
		$query = $this->getQuery();

		$result = $query->select('test_query', IQuery::SELECT_COUNT, ['domain' => 'alpha']);

		Assert::same($result, 4);
	}

	function testAggregation()
	{
		$query = $this->getQuery();

		$builder = new MongoQueryBuilder();
		$builder->select('SUM(size) AS size_total');
		$builder->group('domain');
		$builder->where('size > %i', 10);

		$result1 = $query->select('test_query', $builder->buildAggreregateQuery());

		Assert::same([
			['size_total' => 199, 'domain' => 'beta'],
			['size_total' => 82, 'domain' => 'alpha'],
		], $result1->fetchAll());

		$builder->having('size_total > %i', 82);

		$result2 = $query->select('test_query', $builder->buildAggreregateQuery());

		Assert::same([
			['size_total' => 199, 'domain' => 'beta'],
		], $result2->fetchAll());
	}

	function testAggregationCount()
	{
		$query = $this->getQuery();

		$builder = new MongoQueryBuilder();

		$builder->addSelect('SUM(*) AS count');

		$result1 = $query->select('test_query', $builder->buildAggreregateQuery())->fetch();

		Assert::same(6, $result1['count']);
	}

	function testInsert()
	{
		$query = $this->getQuery();

		$insert = [
			'pr_id%i' => '2',
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points%f[]' => ['18.0', 31.32, 64],
			'type' => 10
		];

		$data = $query->insert('test_query', $insert);

		Assert::type('array', $data);
		Assert::same(['pr_id', 'name', 'domain', 'size', 'points', 'type', '_id'], array_keys($data));
		Assert::type('string', $data['_id']);

		$result = $query->select('test_query', ['!_id'], ['_id = %oid' => $data['_id']])->fetch();
		unset($data['_id']);
		Assert::same($data, $result);
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

	function testUpdateUpsert()
	{
		$query = $this->getQuery();

		$insert = [
			'pr_id%i' => '2',
			'name' => 'Test 7',
			'domain' => 'gama',
			'size' => 101,
			'points%f[]' => ['18.0', 31.32, 64],
			'type' => 10
		];

		$condition = ['domain' => 'gama'];

		$data = $query->update('test_query', $insert, $condition, ['upsert' => TRUE]);

		Assert::same(['_id', 'pr_id', 'name', 'domain', 'size', 'points', 'type'], array_keys($data));
		Assert::type('string', $data['_id']);

		$rows = $query->update('test_query', $insert, $condition, ['upsert' => TRUE]);
		Assert::same(1, $rows);
	}

	function testUpdateMultiple()
	{
		$query = $this->getQuery();

		$conditionBeta = ['domain' => 'beta'];
		$conditionTheta = ['domain' => 'theta'];

		$rows = $query->update('test_query', ['domain' => 'theta'], $conditionBeta, ['multiple' => TRUE]);
		Assert::same(2, $rows);

		$countBeta = $query->select('test_query', IQuery::SELECT_COUNT, $conditionBeta);
		Assert::same(0, $countBeta);

		$countTheta = $query->select('test_query', IQuery::SELECT_COUNT, $conditionTheta);
		Assert::same(2, $countTheta);
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

		$rows = $query->update('test_query', $data, $condition);

		Assert::same(1, $rows);

		$result = $query->select('test_query', ['!_id'], $condition)->fetch();

		Assert::same('test update', $result['name']);
		Assert::false(array_key_exists('domain', $result));
		Assert::false(array_key_exists('type', $result));
		Assert::true(array_key_exists('category', $result));
	}

	function testDelete()
	{
		$query = $this->getQuery();
		$return = $query->delete('test_query', ['pr_id = %i' => 2]);
		Assert::same(3, $return);
	}

}

$test = new MongoQueryTest($connection);
$test->run();






