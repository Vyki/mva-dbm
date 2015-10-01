<?php

namespace Dbm\Tests;

use Mva,
	Tester\Assert,
	Tester\TestCase;

$connection = require __DIR__ . "/../bootstrap.php";

class CollectionAggregationTest extends TestCase
{

	private $connection;

	function __construct($connection)
	{
		$this->connection = $connection;
	}

	protected function setUp()
	{
		exec("mongoimport --db mva_test --drop --collection test_agr < " . __DIR__ . "/test.json");
	}

	/** @return Mva\Mongo\Selection */
	function getCollection()
	{
		return new Mva\Dbm\Selection($this->connection, 'test_agr');
	}

	function testCount()
	{
		$collection = $this->getCollection();

		$count = $collection->count();

		Assert::equal(6, $count);

		$collection->where(['pr_id' => 2]);

		$count2 = $collection->count();

		Assert::equal(3, $count2);
	}

	function testMaxMinSum()
	{
		$collection = $this->getCollection();

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
		$collection = $this->getCollection();
		$collection->select('SUM(size) AS size_total');
		$collection->group('domain');
		$collection->where('size > %i', 10);

		$beta = $collection->fetch();
		
		Assert::true($beta instanceof Mva\Dbm\Document);
		
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

$test = new CollectionAggregationTest($connection);
$test->run();




