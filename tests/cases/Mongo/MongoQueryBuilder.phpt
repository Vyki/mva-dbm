<?php

namespace Dbm\Tests\Mongo;

use Tester\Assert,
	Mva\Dbm\Driver,
	Tester\TestCase;

$database = require __DIR__ . "/../../bootstrap.php";

class MongoQueryBuilderTest extends TestCase
{

	private function getBuilder()
	{
		return new Driver\Mongo\MongoQueryBuilder('grid');
	}

	public function testFrom()
	{
		$builder = $this->getBuilder();
		Assert::same('grid', $builder->from);

		$builder->from('grid_test');
		Assert::same('grid_test', $builder->from);
	}

	public function testWhere()
	{
		$builder = $this->getBuilder();

		$builder->addWhere('domain', 'branch');
		Assert::same(['domain' => 'branch'], $builder->where[0]);

		$builder->addWhere(['domain = %s' => 'branch', 'domain != bus']);
		Assert::same(['domain = %s' => 'branch'], $builder->where[1]);
		Assert::same([0 => 'domain != bus'], $builder->where[2]);

		$builder->where(NULL);
		Assert::same([], $builder->where);
	}

	public function testHaving()
	{
		$builder = $this->getBuilder();

		$builder->addHaving('domain', 'branch');
		Assert::same(['domain' => 'branch'], $builder->having[0]);

		$builder->addHaving(['domain = %s' => 'branch', 'domain != bus']);
		Assert::same(['domain = %s' => 'branch'], $builder->having[1]);
		Assert::same([0 => 'domain != bus'], $builder->having[2]);

		$builder->having(NULL);
		Assert::same([], $builder->having);
	}

	public function testSelect()
	{
		$builder = $this->getBuilder();

		$builder->addSelect('domain');
		Assert::same(['domain'], $builder->select);

		$builder->addSelect('!index');
		Assert::same(['domain', '!index'], $builder->select);

		$builder->select('coord');
		Assert::same(['coord'], $builder->select);

		$builder->select(['id', 'pid']);
		Assert::same(['id', 'pid'], $builder->select);

		$builder->select(NULL);
		Assert::same([], $builder->select);
	}

	public function testOrder()
	{
		$builder = $this->getBuilder();

		$builder->addOrder('domain ASC');
		Assert::same(['domain' => 1], $builder->order);

		$builder->addOrder(['index' => -1, 'coord' => 1]);
		Assert::same(['domain' => 1, 'index' => -1, 'coord' => 1], $builder->order);

		$builder->order('domain DESC');
		Assert::same(['domain' => -1], $builder->order);

		$builder->order(['coord' => 1]);
		Assert::same(['coord' => 1], $builder->order);

		$builder->order(NULL);
		Assert::same([], $builder->order);
	}

	public function testGroup()
	{
		$builder = $this->getBuilder();

		$builder->group(['coord', 'domain']);
		Assert::same(['coord' => '$coord', 'domain' => '$domain'], $builder->group);

		$builder->group('domain');
		Assert::same(['domain' => '$domain'], $builder->group);

		$builder->group(NULL);
		Assert::same([], $builder->group);
	}

	public function testAggregate()
	{
		$builder = $this->getBuilder();

		$builder->addSelect('MAX(domain) AS domain_max');
		Assert::same(['$max' => '$domain'], $builder->aggregate['domain_max']);

		$builder->addSelect('MIN(delta)');
		Assert::same(['$min' => '$delta'], $builder->aggregate['_delta_min']);

		$builder->addAggregate('sum', 'domain', 'dom_sum');
		Assert::same(['$sum' => '$domain'], $builder->aggregate['dom_sum']);

		$builder->addAggregate('sum', '*', 'count');
		Assert::same(['$sum' => 1], $builder->aggregate['count']);

		Assert::same(['_id', 'domain_max', '_delta_min', 'dom_sum', 'count'], array_keys($builder->aggregate));

		$builder->aggregate(NULL);
		Assert::same(FALSE, $builder->aggregate);

		$builder->aggregate('sum', '*', 'count');
		Assert::same(['$sum' => 1], $builder->aggregate['count']);
	}

	public function testLimitOffset()
	{
		$builder = $this->getBuilder();
		$builder->limit(5, 3);

		Assert::same(5, $builder->limit);
		Assert::same(3, $builder->offset);

		$builder->limit(10);
		$builder->offset(6);

		Assert::same(10, $builder->limit);
		Assert::same(6, $builder->offset);
	}

	public function testBuildSelectQuery()
	{
		$builder = $this->getBuilder();

		$builder->select(['_id', 'domain']);
		$builder->where('type = %id', 2);
		$builder->order(['oid ASC', 'domain DESC']);
		$builder->limit(2, 1);

		Assert::same([
			['_id', 'domain'],
			[['type = %id' => 2]],
			[
				'limit' => 2,
				'skip' => 1,
				'sort' => array('oid' => 1, 'domain' => -1),
			],
		], $builder->buildSelectQuery());
	}

	public function testBuildAggregateQuery()
	{
		$builder = $this->getBuilder();
		$builder->select('SUM(size) AS size_total');
		$builder->group('domain');
		$builder->where('size > %i', 10);
		$builder->addSelect('name');
		$builder->having('size_total > %i', 82);
		$builder->order(['oid ASC', 'domain DESC']);
		$builder->limit(2, 1);

		Assert::same([
			['$project' => ['name']],
			['$match' => [['size > %i' => 10]]],
			[
				'$group' => [
					'_id' => ['domain' => '$domain'],
					'size_total' => ['$sum' => '$size'],
				],
			],
			['$sort' => ['oid' => 1, 'domain' => -1]],
			['$match' => [['size_total > %i' => 82]]],
			['$skip' => 1],
			['$limit' => 2]
		], $builder->buildAggreregateQuery());
	}

}

$test = new MongoQueryBuilderTest();
$test->run();
