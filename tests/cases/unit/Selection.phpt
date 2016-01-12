<?php

namespace Dbm\Tests;

use Mockery,
	Tester\Assert,
	Mva\Dbm\Connection,
	Mva\Dbm\Query\IQuery,
	Mva\Dbm\Result\IResult,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Query\QueryBuilder,
	Mva\Dbm\Collection\Selection,
	Mva\Dbm\Collection\Document\IDocumentFactory;

require __DIR__ . "/../../bootstrap.php";

class SelectionTest extends UnitTestCase
{

	/** @var Mockery\MockInterface  */
	private $query;

	/** @var Selection */
	private $selection;

	/** @var Mockery\MockInterface */
	private $connection;

	/** @var Mockery\MockInterface */
	private $queryBuilder;

	/** @var Mockery\MockInterface */
	private $documentFactory;

	/** @var string */
	private $collectionName = 'test_selection';

	protected function setUp()
	{
		parent::setUp();

		$this->query = Mockery::mock(IQuery::class);

		$this->documentFactory = Mockery::mock(IDocumentFactory::class);

		$this->queryBuilder = Mockery::mock(QueryBuilder::class);

		$this->connection = Mockery::mock(Connection::class);
		$this->connection->shouldReceive('getQuery')->between(1, 2)->andReturn($this->query);
		$this->connection->shouldReceive('createQueryBuilder')->between(1, 2)->andReturn($this->queryBuilder);

		$this->selection = new Selection($this->connection, $this->collectionName);
		$this->selection->setDocumentFactory($this->documentFactory);
	}

	function testPrimary()
	{
		Assert::same($this->selection->getPrimary(), '_id');
		$this->selection->setPrimary('id', '%i');
		Assert::same($this->selection->getPrimary(), 'id');
	}

	function testDocumentFactoryGetter()
	{
		Assert::true($this->selection->getDocumentFactory() === $this->documentFactory);
	}

	function testQueryBuilderGetter()
	{
		Assert::true($this->selection->getQueryBuilder() === $this->queryBuilder);
	}

	function testUpdateUpdate()
	{
		$cond = ['a' => ['$gt' => 10]];

		$updata = [
			'id' => 2,
			'name' => 'Test',
			'points' => [18.0, 31.32],
		];

		$this->queryBuilder->where = $cond;

		$this->query->shouldReceive('update')->with($this->collectionName, $updata, $cond, FALSE, TRUE)->once()->andReturn(2);

		Assert::same(2, $this->selection->update($updata));
	}

	function testUpdateUpsert()
	{
		$cond = ['name' => 'test'];

		$updata = [
			'id' => 2,
			'name' => 'Test',
			'points' => [18.0, 31.32],
		];

		$retdata = ['_id' => 'abc12d'] + $updata;
		$retobj = (object) $retdata;

		$this->queryBuilder->where = $cond;

		$this->query->shouldReceive('update')->with($this->collectionName, $updata, $cond, TRUE, TRUE)->andReturn($retdata);

		$this->documentFactory->shouldReceive('create')->with($retdata)->andReturn($retobj);

		Assert::same($retobj, $this->selection->update($updata, TRUE));
	}

	function testInsert()
	{
		$insdata = ['id' => 2, 'name' => 'Test', 'points' => [18.0, 31.32]];

		$retdata = ['_id' => 'abc12d'] + $insdata;
		$retobj = (object) $retdata;

		$this->query->shouldReceive('insert')->with($this->collectionName, $insdata)->andReturn($retdata);

		$this->documentFactory->shouldReceive('create')->with($retdata)->andReturn($retobj);

		$result = $this->selection->insert($insdata);

		Assert::same($retobj, $result);
	}

	function testDelete()
	{
		$cond = ['name' => 'test'];

		$this->queryBuilder->where = $cond;

		$this->query->shouldReceive('delete')->with($this->collectionName, $cond, TRUE)->andReturn(2);
		Assert::same(2, $this->selection->delete());

		$this->query->shouldReceive('delete')->with($this->collectionName, $cond, FALSE)->andReturn(1);
		Assert::same(1, $this->selection->delete(FALSE));
	}

	function testWhere()
	{
		$cond = ['a' => ['$gt' => 10]];

		$this->queryBuilder->shouldReceive('addWhere')->with('name', 'test')->andReturnNull();
		$this->selection->where('name', 'test');

		$this->queryBuilder->shouldReceive('addWhere')->with($cond, [])->andReturnNull();
		$this->selection->where($cond);
	}

	function testWherePrimaryAndSetPrimary()
	{
		$id = 13453;
		$oid = 'abc23dab';

		$this->queryBuilder->shouldReceive('addWhere')->with('_id = %oid', $oid)->andReturnNull();
		$this->selection->wherePrimary($oid);

		$this->selection->setPrimary('id', '%i');

		$this->queryBuilder->shouldReceive('addWhere')->with('id = %i', $id)->andReturnNull();
		$this->selection->wherePrimary($id);
	}

	function testSelect()
	{
		$this->queryBuilder->shouldReceive('addSelect')->with(['!a', 'b', 'c'])->andReturnNull();
		$this->selection->select('!a', 'b', 'c');
	}

	function testOrder()
	{
		$this->queryBuilder->shouldReceive('addOrder')->with(['a ASC', 'b DESC', 'c ASC'])->andReturnNull();
		$this->selection->order('a ASC', 'b DESC', 'c ASC');

		$this->queryBuilder->shouldReceive('order')->with(NULL)->andReturnNull();
		$this->selection->order(NULL);
	}

	function testLimit()
	{
		$this->queryBuilder->shouldReceive('limit')->with(10, NULL)->andReturnNull();
		$this->selection->limit(10);

		$this->queryBuilder->shouldReceive('limit')->with(20, 10)->andReturnNull();
		$this->selection->limit(20, 10);
	}

	function testGroup()
	{
		$this->queryBuilder->shouldReceive('group')->with(['a', 'b'])->andReturnNull();
		$this->selection->group('a', 'b');
	}

	function testHaving()
	{
		$cona = ['a' => ['$gt' => 10]];

		$this->queryBuilder->shouldReceive('addHaving')->with('name', 'test')->andReturnNull();
		$this->selection->having('name', 'test');

		$this->queryBuilder->shouldReceive('addHaving')->with($cona, [])->andReturnNull();
		$this->selection->having($cona);
	}

	function testFetch()
	{
		$data = $this->prepareResultSet();

		Assert::equal($this->selection->fetch(), (object) $data[0]);
		Assert::equal($this->selection->fetch(), (object) $data[1]);
		Assert::equal($this->selection->fetch(), (object) $data[2]);
		Assert::equal($this->selection->fetch(), FALSE);
	}

	function testIterator()
	{
		$data = $this->prepareResultSet();

		foreach ($this->selection as $index => $value) {
			Assert::equal((object) $data[$index], $value);
		}

		Assert::same(2, $index);
	}

	function testFetchAll()
	{
		$data = $this->prepareResultSet();
		$result = $this->selection->fetchAll();

		Assert::type('array', $result);

		foreach ($result as $index => $value) {
			Assert::equal((object) $data[$index], $value);
		}

		Assert::same(2, $index);
	}

	function testFetchPairs()
	{
		$data = $this->prepareResultSet();

		$expected1 = ['1abc', '2abc', '3abc'];
		$expected2 = ['Test 1', 'Test 2', 'Test 3'];
		$expected3 = array_combine($expected1, $expected2);

		Assert::equal($this->selection->fetchPairs(NULL, '_id'), $expected1);
		Assert::equal($this->selection->fetchPairs(NULL, 'name'), $expected2);
		Assert::equal($this->selection->fetchPairs('_id', 'name'), $expected3);

		$i = 0;
		foreach ($this->selection->fetchPairs('_id') as $index => $value) {
			Assert::same($index, $data[$i]['_id']);
			Assert::equal((object) $data[$i], $value);
			++$i;
		}

		Assert::same(3, $i);
	}

	function testCountQuery()
	{
		$this->queryBuilder->shouldReceive('buildSelectQuery')->withNoArgs()->andReturn([[], ['rank' => 10], ['limit' => 20]]);
		$this->query->shouldReceive('count')->with($this->collectionName, ['rank' => 10], ['limit' => 20])->andReturn(14);

		Assert::same(14, $this->selection->count());
	}

	function testCountResult()
	{
		$this->prepareResultSet();
		$this->selection->fetchAll();
		Assert::same(3, $this->selection->count());
	}

	function testAggregation()
	{
		$this->queryBuilder->shouldReceive('importConditions')->with($this->queryBuilder)->andReturnNull();

		$selection = Mockery::mock('Mva\Dbm\Collection\Selection[createSelectionInstance,select,fetch]', [$this->connection, $this->collectionName]);
		$selection->shouldReceive('createSelectionInstance')->withNoArgs()->once()->andReturn($selection);
		$selection->shouldReceive('select')->with('SUM(item) AS _gres')->once()->andReturnNull();
		$selection->shouldReceive('fetch')->withNoArgs()->once()->andReturn((object) ['_gres' => 11]);

		Assert::same(11, $selection->aggregate('SUM', 'item'));
	}

	function testAggregationShortcuts()
	{
		$selection = Mockery::mock('Mva\Dbm\Collection\Selection[aggregate]', [$this->connection, $this->collectionName]);

		$selection->shouldReceive('aggregate')->with('sum', 'item')->once()->andReturn(14);
		Assert::same(14, $selection->count('item'));

		$selection->shouldReceive('aggregate')->with('sum', 'item')->once()->andReturn(15);
		Assert::same(15, $selection->sum('item'));

		$selection->shouldReceive('aggregate')->with('min', 'item')->once()->andReturn(16);
		Assert::same(16, $selection->min('item'));

		$selection->shouldReceive('aggregate')->with('max', 'item')->once()->andReturn(17);
		Assert::same(17, $selection->max('item'));
	}

	function prepareResultSet()
	{
		$data = [
			['_id' => '1abc', 'name' => 'Test 1'],
			['_id' => '2abc', 'name' => 'Test 2'],
			['_id' => '3abc', 'name' => 'Test 3']
		];

		$result = Mockery::mock(IResult::class);
		$result->shouldReceive('getIterator')->withNoArgs()->once()->andReturn(new \ArrayIterator($data));

		$this->documentFactory->shouldReceive('create')->with($data[0])->once()->andReturn((object) $data[0]);
		$this->documentFactory->shouldReceive('create')->with($data[1])->once()->andReturn((object) $data[1]);
		$this->documentFactory->shouldReceive('create')->with($data[2])->once()->andReturn((object) $data[2]);

		$this->queryBuilder->shouldReceive('buildSelectQuery')->withNoArgs()->andReturn([['_id', 'name'], ['rank' => 10], ['limit' => 20]]);

		$this->query->shouldReceive('find')->with($this->collectionName, ['_id', 'name'], ['rank' => 10], ['limit' => 20])->andReturn($result);

		return $data;
	}

}

$test = new SelectionTest();
$test->run();
