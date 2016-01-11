<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini
 */

namespace Dbm\Tests\Driver\Mongo;

use Tester\Assert,
	Mva\Dbm\Query\IQuery,
	Dbm\Tests\DriverTestCase,
	Mva\Dbm\Query\QueryBuilder;

$connection = require __DIR__ . "/../../bootstrap.php";

class QueryMongodbTest extends DriverTestCase
{

	/** @var IQuery */
	private $query;

	protected function setUp()
	{
		$this->loadData('test_query');
		$this->query = $this->getConnection()->getQuery();
	}

	function testFind()
	{
		$result = $this->query->find('test_query', ['!_id', 'name', 'domain'], ['domain = beta'], [IQuery::ORDER => 'name ASC']);

		Assert::equal([['name' => 'Test 5', 'domain' => 'beta'], ['name' => 'Test 6', 'domain' => 'beta']], $result->fetchAll());
	}

	function testFindLimit()
	{
		$conds = ['pr_id = %i' => 2];
		$fields = ['!_id', 'name'];

		$result1 = $this->query->find('test_query', $fields, $conds, [
					IQuery::ORDER => 'name ASC',
					IQuery::LIMIT => 2])->fetchPairs(NULL, 'name');

		Assert::equal(['Test 1', 'Test 2'], $result1);

		$result2 = $this->query->find('test_query', $fields, $conds, [
					IQuery::ORDER => 'name ASC',
					IQuery::LIMIT => 2,
					IQuery::OFFSET => 1])->fetchPairs(NULL, 'name');

		Assert::equal(['Test 2', 'Test 3'], $result2);
	}

	function testDistinct()
	{
		$result = $this->query->distinct('test_query', 'domain');

		$data = $result->fetchAll();

		Assert::same([['domain' => 'alpha'], ['domain' => 'beta']], $data);
	}

	function testCount()
	{
		$result = $this->query->count('test_query', ['domain' => 'alpha']);

		Assert::same($result, 4);
	}

	function testAggregation()
	{
		$builder = new QueryBuilder();
		$builder->select('SUM(size) AS size_total');
		$builder->group('domain');
		$builder->order('_id.domain ASC');
		$builder->where('size > %i', 10);

		$result1 = $this->query->aggregate('test_query', $builder->buildAggreregateQuery())->fetchAll();

		Assert::equal([
			['domain' => 'alpha', 'size_total' => 82],
			['domain' => 'beta', 'size_total' => 199]], $result1);

		$builder->having('size_total > %i', 82);

		$result2 = $this->query->aggregate('test_query', $builder->buildAggreregateQuery());

		Assert::equal([['domain' => 'beta', 'size_total' => 199]], $result2->fetchAll());
	}

	function testInsert()
	{
		$data = [
			'pr_id%i' => '2',
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points%f[]' => ['18.0', 31.3, 64],
			'type' => 10,
			'flag.a' => 'av',
			'flag.b' => 'bw'
		];

		$inserted = [
			'pr_id' => 2,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18.0, 31.3, 64.0],
			'type' => 10,
			'flag' => ['a' => 'av', 'b' => 'bw']
		];

		$result = $this->query->insert('test_query', $data);

		Assert::type('string', $result['_id']);

		$id = $result['_id'];
		unset($result['_id']);

		Assert::equal($inserted, $result);

		$fetched = $this->query->find('test_query', '!_id', ['_id = %oid' => $id])->fetch();

		Assert::equal($inserted, $fetched);
	}

	function testDelete()
	{
		$return = $this->query->delete('test_query', ['pr_id = %i' => 2]);
		Assert::same(3, $return);
	}

	function testUpdate()
	{
		$data = ['domain' => 'theta'];
		$condition = ['_id = %oid' => '54ccf5639ab253f598d6b4a5'];

		$ret = $this->query->update('test_query', $data, $condition);

		Assert::same(1, $ret);

		$result = $this->query->find('test_query', ['domain'], $condition)->fetch();

		Assert::same('theta', $result['domain']);
	}

	function testUpdateUpsert()
	{
		$this->query->onQuery[] = function ($coll, $oper, $param, $res) use (&$log) {
			$log = [$coll, $oper, $param, $res];
		};

		$insert = [
			'pr_id%i' => '2',
			'name' => 'Test 7',
			'domain' => 'gama',
			'size' => 101,
			'points%f[]' => ['18.0', 31.32, 64],
			'type' => 10
		];

		$condition = ['domain' => 'gama'];

		$upserted = $this->query->update('test_query', $insert, $condition, TRUE);

		Assert::type('string', $upserted['_id']);

		$insert['name'] = 'Test 77';

		$rows = $this->query->update('test_query', $insert, $condition, TRUE);

		Assert::same(1, $rows);
	}

	function testUpdateMultiple()
	{
		$conditionBeta = ['domain' => 'beta'];
		$conditionTheta = ['domain' => 'theta'];

		$rows = $this->query->update('test_query', $conditionTheta, $conditionBeta, FALSE, TRUE);
		Assert::same(2, $rows);

		$countBeta = $this->query->count('test_query', $conditionBeta);
		Assert::same(0, $countBeta);

		$countTheta = $this->query->count('test_query', $conditionTheta);
		Assert::same(2, $countTheta);
	}

	function testUpdateManipulation()
	{
		$condition = ['_id = %oid' => '54ccf3509ab253f598d6b4a0'];

		$data = [
			'size' => 40,
			'$set' => ['name' => 'test update'],
			'$unset' => ['domain'],
			'$rename' => ['type' => 'category']
		];

		$rows = $this->query->update('test_query', $data, $condition);

		Assert::same(1, $rows);

		$result = $this->query->find('test_query', ['!_id'], $condition)->fetch();

		Assert::same('test update', $result['name']);
		Assert::false(array_key_exists('domain', $result));
		Assert::false(array_key_exists('type', $result));
		Assert::true(array_key_exists('category', $result));
	}

}

$test = new QueryMongodbTest();
$test->run();






