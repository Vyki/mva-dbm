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

class SelectionAggregationTest extends DriverTestCase
{

	/** @var Selection */
	private $selection;

	protected function setUp()
	{
		$this->loadData('test_aggregate');
		$this->selection = $this->getConnection()->getSelection('test_aggregate');
	}

	function testCount()
	{
		Assert::equal(6, $this->selection->count('*'));
		Assert::equal(3, $this->selection->where(['pr_id' => 2])->count('*'));
	}

	function testMaxMinSum()
	{
		$max = $this->selection->max('size');
		$min = $this->selection->min('size');

		Assert::equal(101, $max);
		Assert::equal(10, $min);

		$this->selection->where('domain', 'beta');

		$sum = $this->selection->sum('size');
		Assert::equal(199, $sum);

		$sum_not_number = $this->selection->sum('domain');
		Assert::equal(0, $sum_not_number);

		$sum_undefined = $this->selection->sum('fake');
		Assert::equal(0, $sum_undefined);
	}

	function testFullAggregation()
	{
		$this->selection->select('SUM(size) AS size_total');
		$this->selection->group('domain');
		$this->selection->where('size > %i', 10);
		$this->selection->order('_id.domain DESC');

		$beta = $this->selection->fetch();

		Assert::true($beta instanceof Document);

		Assert::equal('beta', $beta->domain);
		Assert::equal(199, $beta->size_total);

		$alpha = $this->selection->fetch();

		Assert::equal('alpha', $alpha->domain);
		Assert::equal(82, $alpha->size_total);

		Assert::equal(2, $this->selection->count());

		$this->selection->having('size_total > %i', 82);

		$having_test = $this->selection->fetch();

		Assert::equal(1, $this->selection->count());
		Assert::equal('beta', $having_test->domain);
		Assert::equal(199, $having_test->size_total);
	}

}

$test = new SelectionAggregationTest();
$test->run();




