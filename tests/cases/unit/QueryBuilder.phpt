<?php

namespace Dbm\Tests;

use Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Query\IQuery,
	Mva\Dbm\Query\QueryBuilder;

$database = require __DIR__ . "/../../bootstrap.php";

class MongoQueryBuilderTest extends TestCase
{

	/** @var QueryBuilder */
	private $builder;

	function setUp()
	{
		parent::setUp();
		$this->builder = new QueryBuilder;
	}

	public function testFrom()
	{

		$this->builder->from('grid_test');
		Assert::same('grid_test', $this->builder->from);
	}

	public function testWhere()
	{
		$this->builder->addWhere('domain', 'branch');
		Assert::same(['domain' => 'branch'], $this->builder->where[0]);

		$this->builder->addWhere(['domain = %s' => 'branch', 'domain != bus']);
		Assert::same(['domain = %s' => 'branch'], $this->builder->where[1]);
		Assert::same([0 => 'domain != bus'], $this->builder->where[2]);

		$this->builder->where(NULL);
		Assert::same([], $this->builder->where);
	}

	public function testHaving()
	{
		$this->builder->addHaving('domain', 'branch');
		Assert::same(['domain' => 'branch'], $this->builder->having[0]);

		$this->builder->addHaving(['domain = %s' => 'branch', 'domain != bus']);
		Assert::same(['domain = %s' => 'branch'], $this->builder->having[1]);
		Assert::same([0 => 'domain != bus'], $this->builder->having[2]);

		$this->builder->having(NULL);
		Assert::same([], $this->builder->having);
	}

	public function testSelect()
	{
		$this->builder->addSelect('domain');
		Assert::same(['domain'], $this->builder->select);

		$this->builder->addSelect('!index');
		Assert::same(['domain', '!index'], $this->builder->select);

		$this->builder->select('coord');
		Assert::same(['coord'], $this->builder->select);

		$this->builder->select(['id', 'pid']);
		Assert::same(['id', 'pid'], $this->builder->select);

		$this->builder->select(NULL);
		Assert::same([], $this->builder->select);
	}

	public function testOrder()
	{
		$this->builder->order(['coord' => 1]);
		Assert::same(['coord' => 1], $this->builder->order);

		$this->builder->addOrder(['domain' => -1]);
		Assert::same(['coord' => 1, 'domain' => -1], $this->builder->order);

		$this->builder->order(['index' => 1]);
		Assert::same(['index' => 1], $this->builder->order);

		$this->builder->order(NULL);
		Assert::same([], $this->builder->order);
	}

	public function testGroup()
	{
		$this->builder->group(['coord', 'domain']);
		Assert::same(['coord' => '$coord', 'domain' => '$domain'], $this->builder->group);

		$this->builder->group('domain');
		Assert::same(['domain' => '$domain'], $this->builder->group);

		$this->builder->group(NULL);
		Assert::same([], $this->builder->group);
	}

	public function testAggregate()
	{
		$this->builder->addSelect('MAX(a.domain) AS domain_max');
		Assert::same(['$max' => '$a.domain'], $this->builder->aggregate['domain_max']);

		$this->builder->addSelect('MIN(delta)');
		Assert::same(['$min' => '$delta'], $this->builder->aggregate['_delta_min']);

		$this->builder->addAggregate('sum', 'domain', 'dom_sum');
		Assert::same(['$sum' => '$domain'], $this->builder->aggregate['dom_sum']);

		$this->builder->addAggregate('sum', '*', 'count');
		Assert::same(['$sum' => 1], $this->builder->aggregate['count']);

		Assert::same(['_id', 'domain_max', '_delta_min', 'dom_sum', 'count'], array_keys($this->builder->aggregate));

		$this->builder->aggregate(NULL);
		Assert::same(FALSE, $this->builder->aggregate);

		$this->builder->aggregate('sum', '*', 'count');
		Assert::same(['$sum' => 1], $this->builder->aggregate['count']);
	}

	public function testLimitOffset()
	{
		$this->builder->limit(5, 3);

		Assert::same(5, $this->builder->limit);
		Assert::same(3, $this->builder->offset);

		$this->builder->limit(10);
		$this->builder->offset(6);

		Assert::same(10, $this->builder->limit);
		Assert::same(6, $this->builder->offset);
	}

	public function testBuildSelectQuery()
	{
		$this->builder->select(['_id', 'domain']);
		$this->builder->where('type = %id', 2);
		$this->builder->order(['oid ASC', 'domain DESC']);
		$this->builder->limit(2, 1);

		Assert::same([
			['_id', 'domain'],
			[['type = %id' => 2]],
			[
				'limit' => 2,
				'skip' => 1,
				'sort' => ['oid ASC', 'domain DESC'],
			]], $this->builder->buildSelectQuery());
	}

	public function testBuildAggregateQuery()
	{
		$this->builder->select('SUM(size) AS size_total');
		$this->builder->group('domain');
		$this->builder->where('size > %i', 10);
		$this->builder->addSelect('name');
		$this->builder->having('size_total > %i', 82);
		$this->builder->order(['oid ASC', 'domain DESC']);
		$this->builder->limit(2, 1);

		Assert::same([
			['$project' => ['name']],
			['$match' => [['size > %i' => 10]]],
			[
				'$group' => [
					'_id' => ['domain' => '$domain'],
					'size_total' => ['$sum' => '$size'],
				],
			],
			['$sort' => ['oid ASC', 'domain DESC']],
			['$match' => [['size_total > %i' => 82]]],
			['$skip' => 1],
			['$limit' => 2]], $this->builder->buildAggreregateQuery());
	}

}

$test = new MongoQueryBuilderTest();
$test->run();
