<?php

namespace Dbm\Tests\Collection;

use Mva,
	Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Collection\Document,
	Mva\Dbm\Collection\Selection;

$connection = require __DIR__ . "/../../bootstrap.php";

class SelectionAggregationTest extends TestCase
{

	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_agr < " . __DIR__ . "/../test.txt");
	}

	/** @return Mva\Mongo\Selection */
	function getSelection()
	{
		return new Selection($this->connection, 'test_agr');
	}

	function testCount()
	{
		$collection = $this->getSelection();

		$count = $collection->count();

		Assert::equal(6, $count);

		$collection->where(['pr_id' => 2]);

		$count2 = $collection->count();

		Assert::equal(3, $count2);
	}

	function testMaxMinSum()
	{
		$collection = $this->getSelection();

		$max = $collection->max('size');
		$min = $collection->min('size');

		Assert::equal(101, $max);
		Assert::equal(10, $min);

		$collection->where('domain', 'beta');

		$sum = $collection->sum('size');
		Assert::equal(199, $sum);

		$sum_not_number = $collection->sum('domain');
		Assert::equal(0, $sum_not_number);

		$sum_undefined = $collection->sum('fake');
		Assert::equal(0, $sum_undefined);
	}

	function testFullAggregation()
	{
		$collection = $this->getSelection();
		$collection->select('SUM(size) AS size_total');
		$collection->group('domain');
		$collection->where('size > %i', 10);

		$beta = $collection->fetch();

		Assert::true($beta instanceof Document);

		Assert::equal('beta', $beta->domain);
		Assert::equal(199, $beta->size_total);

		$alpha = $collection->fetch();

		Assert::equal('alpha', $alpha->domain);
		Assert::equal(82, $alpha->size_total);

		Assert::equal(2, $collection->count());

		$collection->having('size_total > %i', 82);

		$having_test = $collection->fetch();

		Assert::equal(1, $collection->count());
		Assert::equal('beta', $having_test->domain);
		Assert::equal(199, $having_test->size_total);
	}

}

$test = new SelectionAggregationTest($connection);
$test->run();




