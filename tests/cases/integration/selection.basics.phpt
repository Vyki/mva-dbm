<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini
 */

namespace Dbm\Tests\Collection;

use Tester\Assert,
	Dbm\Tests\DriverTestCase,
	Mva\Dbm\Collection\Selection,
	Mva\Dbm\Collection\Document\Document;

$connection = require __DIR__ . "/../../bootstrap.php";

class SelectionBasicsTest extends DriverTestCase
{

	/** @var Selection */
	private $selection;

	protected function setUp()
	{
		$this->loadData('test_selection_basics');
		$this->selection = $this->getConnection()->getSelection('test_selection_basics');
	}

	function testWherePrimary()
	{
		$this->selection->wherePrimary('5bce658d5b');

		$this->selection->setPrimary('pid');
		$this->selection->wherePrimary('4bceacb8db');

		$this->selection->setPrimary('domain_id', '%s');
		$this->selection->wherePrimary('abce658d5c');

		Assert::same([
			['_id = %oid' => '5bce658d5b'],
			['pid = %oid' => '4bceacb8db'],
			['domain_id = %s' => 'abce658d5c']], $this->selection->getQueryBuilder()->where);
	}

	function testWhere()
	{
		$this->selection->where('size < %i', 100);

		$this->selection->where([
			['pr_id' => 2],
			['domain' => ['alpha', 'beta']]
		]);

		Assert::count(3, $this->selection->getQueryBuilder()->where);
	}

	function testSelect()
	{
		$this->selection->select('domain', '!type');

		Assert::same(['domain', '!type'], $this->selection->getQueryBuilder()->select);
	}

	function testFetch()
	{
		$this->selection->select('domain', 'type', 'name');

		$this->selection->where(['pr_id' => 2, 'size' => 10]);

		$document = $this->selection->fetch();

		Assert::true($document instanceof Document);

		Assert::same(['_id', 'name', 'domain', 'type'], array_keys($document->toArray()));
	}

	function testGet()
	{
		$item = $this->selection->get('54ccf3509ab253f598d6b4a0');
		Assert::count(7, $item);
		Assert::same($item->domain, 'alpha');
		Assert::same($item->type, 9);
	}

	function testIterator()
	{
		$this->selection->select('domain', 'type', 'name');

		$this->selection->where('pr_id', 2);

		foreach ($this->selection as $index => $row) {
			Assert::true($row instanceof Document);
			Assert::same(['_id', 'name', 'domain', 'type'], array_keys($row->toArray()));
		}

		Assert::equal(2, $index);
	}

	function testFetchAll()
	{
		$this->selection->select('domain', 'type', 'name');

		$this->selection->where('pr_id', 2);

		$data = $this->selection->fetchAll();

		Assert::type('array', $data);

		foreach ($data as $index => $row) {
			Assert::true($row instanceof Document);
			Assert::equal(['_id', 'name', 'domain', 'type'], array_keys($row->toArray()));
		}

		Assert::same(2, $index);
	}

	function testFetchPairs()
	{
		$this->selection->select('domain', 'pr_id', '!_id')->where('pr_id', 1)->order('domain ASC');

		$expected1 = ['alpha' => ['pr_id' => 1, 'domain' => 'alpha'], 'beta' => ['pr_id' => 1, 'domain' => 'beta']];
		$expected2 = array_keys($expected1);

		foreach ($this->selection->fetchPairs('domain') as $index => $value) {
			Assert::equal($expected1[$index], $value->toArray());
		}

		foreach ($this->selection->fetchPairs(NULL, 'domain') as $index => $value) {
			Assert::equal($expected2[$index], $value);
		}

		Assert::equal(['alpha' => 1, 'beta' => 1], $this->selection->fetchPairs('domain', 'pr_id'));
	}

	function testInsert()
	{
		$insert = [
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $this->selection->insert($insert);

		Assert::true($ret instanceof Document);
		Assert::true(isset($ret->_id));

		$data = $this->selection->get($ret->_id);

		Assert::equal($data, $ret);
	}

	function testUpdate()
	{
		$ret = $this->selection->where('name', 'Test 6')->update(['domain' => 'alpha']);

		$data = $this->selection->fetch();

		Assert::same($ret, 1);
		Assert::same('alpha', $data->domain);
	}

	function testUpsert()
	{
		$upsert = [
			'pr_id' => 3,
			'name' => 'Test 7',
			'domain' => 'theta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$ret = $this->selection->where('domain', 'theta')->update($upsert, TRUE);

		Assert::true($ret instanceof Document);
		Assert::true(isset($ret->_id));

		$data = $this->selection->get($ret->_id);

		Assert::equal($data, $ret);
	}

	function testOrder()
	{
		$this->selection->select('domain', 'name', '!_id')->where('pr_id', 1);

		$result1 = $this->selection->order('domain ASC')->fetchPairs('name', 'domain');
		Assert::same($this->selection->getQueryBuilder()->order, ['domain ASC']);

		$this->selection->order(NULL);
		Assert::same($this->selection->getQueryBuilder()->order, []);

		$result2 = $this->selection->order('name DESC')->fetchPairs('name', 'domain');
		Assert::same($this->selection->getQueryBuilder()->order, ['name DESC']);

		Assert::same(['Test 4' => 'alpha', 'Test 5' => 'beta'], $result1);
		Assert::same(['Test 5' => 'beta', 'Test 4' => 'alpha'], $result2);
	}

	function testUpdateManipulation()
	{
		$return = $this->selection->where('pr_id', 1)->update([
			'size' => 40,
			'$set' => ['name' => 'test update'],
			'$unset' => ['domain'], //or 'domain' for singe item
			'$rename' => ['type' => 'category']
		]);

		Assert::same(2, $return);

		foreach ($this->selection as $index => $data) {
			Assert::same(40, $data->size);

			Assert::same('test update', $data->name);

			Assert::false(isset($data->type));

			Assert::true(isset($data->category));

			Assert::false(isset($data->domain));
		}

		Assert::same(1, $index);
	}

}

$test = new SelectionBasicsTest();
$test->run();






