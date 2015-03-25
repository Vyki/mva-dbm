<?php

namespace Test;

use Mva,
	Tester\Assert,
	Tester\TestCase;

$database = require __DIR__ . "/../bootstrap.php";

class FindTest extends TestCase
{

	private $database;

	function __construct($database)
	{
		$this->database = $database;
	}

	/** @return Mva\Mongo\Selection */
	function getSelection()
	{
		exec("mongoimport --db mva_test --drop --collection test_find < " . __DIR__ . "/test.json");
		return new Mva\Mongo\Collection('test_find', $this->database);
	}

	function testWhere()
	{
		$collection = $this->getSelection();

		$collection->where('size < ?', 100);

		Assert::same($collection->paramBuilder->where, ['size' => ['$lt' => 100]]);

		$collection->where([
			['pr_id' => 2],
			['domain' => ['alpha', 'beta']]
		]);

		$expwhere = ['$and' => [
				['size' => ['$lt' => 100]],
				['pr_id' => 2],
				['domain' => ['$in' => ['alpha', 'beta']]],
		]];

		Assert::same($expwhere, $collection->paramBuilder->where);
	}
	
	function testSelect()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'type');

		Assert::same(['domain' => TRUE, 'type' => TRUE], $collection->paramBuilder->select);
	}

	function testUnselect()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'type')->unselect('_id');

		Assert::same(['domain' => TRUE, 'type' => TRUE, '_id' => FALSE], $collection->paramBuilder->select);
	}

	function testFetch()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'type', 'name');

		$collection->where(['pr_id' => 2, 'size' => 10]);

		$document = $collection->fetch();

		Assert::true($document instanceof Mva\Mongo\Document);

		Assert::same(['_id', 'name', 'domain', 'type'], array_keys($document->toArray()));
	}

	function testFetchAll()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'type', 'name');

		$collection->where('pr_id', 2);

		$i = 0;

		foreach ($collection as $row) {
			++$i;
			Assert::true($row instanceof Mva\Mongo\Document);
			Assert::same(['_id', 'name', 'domain', 'type'], array_keys($row->toArray()));
		}

		Assert::equal(3, $i);
	}

	function testFindPairs()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'pr_id')->unselect('_id')->where('pr_id', 1);

		$data1 = $collection->fetchPairs('domain');

		$expected = ['alpha' => ['pr_id' => 1, 'domain' => 'alpha'], 'beta' => ['pr_id' => 1, 'domain' => 'beta']];

		Assert::same($expected, $data1);

		$data2 = $collection->fetchPairs('domain', 'pr_id');

		Assert::same(['alpha' => 1, 'beta' => 1], $data2);
	}

	function testFetchAssoc()
	{
		$collection = $this->getSelection();

		$collection->select('domain', 'pr_id');

		$data = $collection->fetchAssoc('domain');

		Assert::same(['alpha', 'beta'], array_keys($data));

		Assert::count(4, $data['alpha']);

		Assert::count(2, $data['beta']);
	}

	function testInsert()
	{
		$collection = $this->getSelection();

		$insert = [
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $collection->insert($insert);

		Assert::true(array_key_exists('_id', $ret));

		$data = $collection->wherePrimary($ret['_id'])->fetch()->toArray();

		Assert::same(ksort($ret, SORT_STRING), ksort($data, SORT_STRING));
	}

	function testUpdate()
	{
		$collection = $this->getSelection();

		$collection->where('name', 'Test 6')->update(['domain' => 'alpha']);

		$data = $collection->fetch();

		Assert::same('alpha', $data['domain']);
	}

	function testUpdateManipulation()
	{
		$collection = $this->getSelection();

		$collection->where('pr_id', 1)->update([
			'size' => 40,
			'$set' => ['name' => 'test update'],
			'$unset' => ['domain'], //or 'domain' for singe item
			'$rename' => ['type' => 'category'] 
		]);

		foreach ($collection as $data) {
			Assert::same(40, $data['size']);
			
			Assert::same('test update', $data['name']);
			
			Assert::false(isset($data['type']));
			
			Assert::true(isset($data['category']));
			
			Assert::false(isset($data['domain']));
		}
	}

}

$test = new FindTest($database);
$test->run();






