<?php

namespace Dbm\Tests;

use Mva,
	Tester\Assert,
	Tester\TestCase;

$connection = require __DIR__ . "/../bootstrap.php";

class CollectionBaseTest extends TestCase
{

	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_find < " . __DIR__ . "/test.json");
	}

	/** @return Mva\Mongo\Selection */
	function getCollection()
	{
		return new Mva\Dbm\Selection($this->connection, 'test_find');
	}

	function testWhere()
	{
		$collection = $this->getCollection();

		$collection->where('size < %i', 100);

		$collection->where([
			['pr_id' => 2],
			['domain' => ['alpha', 'beta']]
		]);

		Assert::count(3, $collection->queryBuilder->where);
	}

	function testSelect()
	{
		$collection = $this->getCollection();

		$collection->select('domain', '!type');

		Assert::same(['domain', '!type'], $collection->queryBuilder->select);
	}

	function testFetch()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type', 'name');

		$collection->where(['pr_id' => 2, 'size' => 10]);

		$document = $collection->fetch();

		Assert::true($document instanceof Mva\Dbm\Document);

		Assert::same(['_id', 'name', 'domain', 'type'], array_keys($document->toArray()));
	}

	function testFetchAll()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type', 'name');

		$collection->where('pr_id', 2);

		$i = 0;

		foreach ($collection as $row) {
			++$i;
			Assert::true($row instanceof Mva\Dbm\Document);
			Assert::same(['_id', 'name', 'domain', 'type'], array_keys($row->toArray()));
		}

		Assert::equal(3, $i);
	}

	function testFetchPairs()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'pr_id', '!_id')->where('pr_id', 1);

		$expected1 = ['alpha' => ['pr_id' => 1, 'domain' => 'alpha'], 'beta' => ['pr_id' => 1, 'domain' => 'beta']];
		$expected2 = array_keys($expected1);

		foreach ($collection->fetchPairs('domain') as $index => $value) {
			Assert::same($expected1[$index], $value->toArray());
		}

		foreach ($collection->fetchPairs(NULL, 'domain') as $index => $value) {
			Assert::same($expected2[$index], $value);
		}

		Assert::same(['alpha' => 1, 'beta' => 1], $collection->fetchPairs('domain', 'pr_id'));
	}

	function testFetchAssoc()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'pr_id');

		$data = $collection->fetchAssoc('domain[]');

		Assert::same(['alpha', 'beta'], array_keys($data));

		Assert::count(4, $data['alpha']);

		Assert::count(2, $data['beta']);
	}

	function testInsert()
	{
		$collection = $this->getCollection();

		$insert = [
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $collection->insert($insert);

		Assert::true($ret instanceof Mva\Dbm\Document);
		Assert::true(isset($ret->_id));
		Assert::true(isset($collection[$ret->_id]));

		$data = $collection->wherePrimary($ret->_id)->fetch()->toArray();

		$retarr = $ret->toArray();

		Assert::same(ksort($retarr, SORT_STRING), ksort($data, SORT_STRING));
	}

	function testUpdate()
	{
		$collection = $this->getCollection();

		$ret = $collection->where('name', 'Test 6')->update(['domain' => 'alpha']);

		$data = $collection->fetch();

		Assert::same($ret, 1);
		Assert::same('alpha', $data->domain);
	}

	function testUpsert()
	{
		$collection = $this->getCollection();

		$upsert = [
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'theta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $collection->where('domain', 'theta')->update($upsert, TRUE);

		Assert::true($ret instanceof Mva\Dbm\Document);
		Assert::true(isset($ret->_id));
		Assert::true(isset($collection[$ret->_id]));

		$data = $collection->wherePrimary($ret->_id)->fetch()->toArray();

		$retarr = $ret->toArray();

		Assert::same(ksort($retarr, SORT_STRING), ksort($data, SORT_STRING));
	}

	function testUpdateManipulation()
	{
		$collection = $this->getCollection();

		$collection->where('pr_id', 1)->update([
			'size' => 40,
			'$set' => ['name' => 'test update'],
			'$unset' => ['domain'], //or 'domain' for singe item
			'$rename' => ['type' => 'category']
		]);

		foreach ($collection as $data) {
			Assert::same(40, $data->size);

			Assert::same('test update', $data->name);

			Assert::false(isset($data->type));

			Assert::true(isset($data->category));

			Assert::false(isset($data->domain));
		}
	}

	function testLimit()
	{
		$fullrecord = $this->getCollection()->where('pr_id', 2);

		Assert::same(3, $fullrecord->count());

		$limit1 = $this->getCollection()->limit(1, 1);
		//gets second record
		$first = $limit1->fetch();

		$limit2 = $this->getCollection()->limit(2);
		//skip first record
		$limit2->fetch();
		//gets second record
		$second = $limit2->fetch();

		Assert::same((string) $first->_id, (string) $second->_id);

		Assert::same(1, $limit1->count());

		Assert::same(2, $limit2->count());
	}

}

$test = new CollectionBaseTest($connection);
$test->run();






