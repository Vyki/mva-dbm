<?php

namespace Test;

use Mva,
	Tester\Assert,
	Tester\TestCase;

$database = require __DIR__ . "/../bootstrap.php";

class CollectionBaseTest extends TestCase
{

	private $database;

	function __construct($database)
	{
		$this->database = $database;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_find < " . __DIR__ . "/test.json");
	}

	/** @return Mva\Mongo\Selection */
	function getCollection()
	{
		return new Mva\Mongo\Collection('test_find', $this->database);
	}

	function testWhere()
	{
		$collection = $this->getCollection();

		$collection->where('size < ?', 100);

		Assert::same($collection->paramBuilder->where, array('size' => array('$lt' => 100)));

		$collection->where(array(
			array('pr_id' => 2),
			array('domain' => array('alpha', 'beta'))
		));

		$expwhere = array('$and' => array(
				array('size' => array('$lt' => 100)),
				array('pr_id' => 2),
				array('domain' => array('$in' => array('alpha', 'beta'))),
		));

		Assert::same($expwhere, $collection->paramBuilder->where);
	}

	function testSelect()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type');

		Assert::same(array('domain' => TRUE, 'type' => TRUE), $collection->paramBuilder->select);
	}

	function testUnselect()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type')->unselect('_id');

		Assert::same(array('domain' => TRUE, 'type' => TRUE, '_id' => FALSE), $collection->paramBuilder->select);
	}

	function testFetch()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type', 'name');

		$collection->where(array('pr_id' => 2, 'size' => 10));

		$document = $collection->fetch();

		Assert::true($document instanceof Mva\Mongo\Document);

		Assert::same(array('_id', 'name', 'domain', 'type'), array_keys($document->toArray()));
	}

	function testFetchAll()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'type', 'name');

		$collection->where('pr_id', 2);

		$i = 0;

		foreach ($collection as $row) {
			++$i;
			Assert::true($row instanceof Mva\Mongo\Document);
			Assert::same(array('_id', 'name', 'domain', 'type'), array_keys($row->toArray()));
		}

		Assert::equal(3, $i);
	}

	function testFetchPairs()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'pr_id')->unselect('_id')->where('pr_id', 1);

		$data1 = $collection->fetchPairs('domain');

		$expected = array('alpha' => array('pr_id' => 1, 'domain' => 'alpha'), 'beta' => array('pr_id' => 1, 'domain' => 'beta'));

		Assert::same($expected, $data1);

		$data2 = $collection->fetchPairs('domain', 'pr_id');

		Assert::same(array('alpha' => 1, 'beta' => 1), $data2);
	}

	function testFetchAssoc()
	{
		$collection = $this->getCollection();

		$collection->select('domain', 'pr_id');

		$data = $collection->fetchAssoc('domain[]');

		Assert::same(array('alpha', 'beta'), array_keys($data));

		Assert::count(4, $data['alpha']);

		Assert::count(2, $data['beta']);
	}

	function testInsert()
	{
		$collection = $this->getCollection();

		$insert = array(
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => array(18, 31, 64),
			'type' => 10
		);

		$ret = $collection->insert($insert);

		Assert::true(array_key_exists('_id', $ret));

		$data = $collection->wherePrimary($ret['_id'])->fetch()->toArray();

		Assert::same(ksort($ret, SORT_STRING), ksort($data, SORT_STRING));
	}

	function testUpdate()
	{
		$collection = $this->getCollection();

		$collection->where('name', 'Test 6')->update(array('domain' => 'alpha'));

		$data = $collection->fetch();

		Assert::same('alpha', $data['domain']);
	}

	function testUpdateManipulation()
	{
		$collection = $this->getCollection();

		$collection->where('pr_id', 1)->update(array(
			'size' => 40,
			'$set' => array('name' => 'test update'),
			'$unset' => array('domain'), //or 'domain' for singe item
			'$rename' => array('type' => 'category')
		));

		foreach ($collection as $data) {
			Assert::same(40, $data['size']);

			Assert::same('test update', $data['name']);

			Assert::false(isset($data['type']));

			Assert::true(isset($data['category']));

			Assert::false(isset($data['domain']));
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

		Assert::same((string) $first['_id'], (string) $second['_id']);

		Assert::same(1, $limit1->count());

		Assert::same(2, $limit2->count());
	}

}

$test = new CollectionBaseTest($database);
$test->run();






