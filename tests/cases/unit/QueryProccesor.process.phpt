<?php

namespace Dbm\Tests;

use Mockery,
	Tester\Assert,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Query\QueryProcessor;

require __DIR__ . "/../../bootstrap.php";

class QueryProcessorProcessTest extends UnitTestCase
{

	/** @var IDriver */
	private $driver;

	/** @var MongoQueryProcessor */
	private $preprocessor;

	function setUp()
	{
		parent::setUp();

		$this->driver = Mockery::mock(IDriver::class);
		$this->preprocessor = new QueryProcessor($this->driver);
	}

	function testProcessSelect()
	{
		$select1 = ['name' => TRUE, 'domain' => FALSE, '!item.subitem' => TRUE];

		Assert::same($select1, $this->preprocessor->processSelect($select1));

		$select2 = ['name', '!domain', '!!item.subitem'];

		Assert::same($select1, $this->preprocessor->processSelect($select2));
	}

	function testProcessOrder()
	{
		$order1 = ['a' => 1, 'b' => -1];
		$order2 = ['a ASC', 'b DESC'];

		Assert::equal($order1, $this->preprocessor->processOrder($order1));
		Assert::equal($order1, $this->preprocessor->processOrder($order2));

		Assert::exception(function() {
			$this->preprocessor->processOrder(['a KFC', 'b DESC']);
		}, '\Mva\Dbm\InvalidArgumentException', 'Invalid order parameter: a KFC');

		Assert::exception(function() {
			$this->preprocessor->processOrder([['a', 'b'], 'b DESC']);
		}, '\Mva\Dbm\InvalidArgumentException', 'Invalid order parameter: array');
	}

	function testProcessData()
	{
		$actual = $this->preprocessor->processData([
			'type%s' => 'vehicle',
			'width%i' => '27',
			'height%f' => '34.4',
			'positive%b' => 1,
			'%%message%%s' => 'test',
			'%%message%%%s' => 'test',
			'samples%f[]' => ['1', '3.3', 3.4, 3],
			'_id' => '54ccf5639ab253f598d6b4a5',
		]);

		$expected = [
			'type' => 'vehicle',
			'width' => 27,
			'height' => 34.4,
			'positive' => TRUE,
			'%message%s' => 'test',
			'%message%' => 'test',
			'samples' => [1.0, 3.3, 3.4, 3.0],
			'_id' => $actual['_id'],
		];

		Assert::same($expected, $actual);

		$return = (object) ['sec' => 946684923];
		
		$this->driver->shouldReceive('convertToDriver')->once()->with(946684923, 'dt')->andReturn($return);
		
		$expdate = $this->preprocessor->processData(['date' => new \DateTime('2000-01-01 01:02:03')]);

		Assert::same($return, $expdate['date']);
	}

	function processDataRecursive()
	{
		$actual = $this->preprocessor->processData([
			'width%i' => '27',
			'height%f' => '34.4',
			'samples' => [
				'one%s' => 12.3,
				'two%i[]' => ['12', 13.3, 5]
			]
		]);

		$expected = [
			'width' => 27,
			'height' => 34.4,
			'samples' => [
				'one' => '12.3',
				'two' => [12, 13, 5]
			]
		];

		Assert::same($expected, $actual);
	}

	function testProcessDataExpandRow()
	{
		$actual = $this->preprocessor->processData([
			'name' => 'Roman',
			'coords.x%i' => '12',
			'coords.y' => 15,
			'city.0' => 'Pilsen',
			'city.1' => 'Prague'], TRUE);

		$expected = [
			'name' => 'Roman',
			'coords' => ['x' => 12, 'y' => 15],
			'city' => ['Pilsen', 'Prague']
		];

		Assert::same($actual, $expected);
	}

	function testProcessUpdate()
	{
		$data = [
			'size%f' => 40,
			'$set' => ['name' => 'test update', 'rank%i' => '13.21'],
			'$setOnInsert' => ['limit%i' => '10'],
			'$unset' => ['domain'],
			'$addToSet' => ['score%i' => '89'],
			'$rename' => ['type' => 'category'],
			'$push' => [
				'quizzes' => [
					'$each' => [['wk%i' => '5', 'score%f' => 8], ['wk%i' => '4', 'score%f' => 7]],
					'$sort' => ['score' => -1],
					'$slice' => 3
				]
			]
		];

		$expected = [
			'$set' => ['name' => 'test update', 'rank' => 13, 'size' => 40.0],
			'$setOnInsert' => ['limit' => 10],
			'$unset' => ['domain' => ''],
			'$addToSet' => ['score' => 89],
			'$rename' => ['type' => 'category'],
			'$push' => [
				'quizzes' => [
					'$each' => [['wk' => 5, 'score' => 8.0], ['wk' => 4, 'score' => 7.0]],
					'$sort' => ['score' => -1],
					'$slice' => 3
				]
			]
		];

		Assert::same($expected, $this->preprocessor->processUpdate($data));
	}

	function testProcessCondition_operators()
	{
		$testArray1 = ['bus', 2, 'branch'];
		$testArray2 = array_combine($testArray1, $testArray1);

		//where test
		$cond1 = ['domain' => 'branch'];
		Assert::same(['domain' => 'branch'], $this->preprocessor->processCondition($cond1));

		$cond2 = ['domain = %s' => 'branch'];
		Assert::same(['domain' => 'branch'], $this->preprocessor->processCondition($cond2));

		$cond3 = ['domain = bus'];
		Assert::same(['domain' => 'bus'], $this->preprocessor->processCondition($cond3));

		$cond4 = ['domain <> %s' => 'branch'];
		Assert::same(['domain' => ['$ne' => 'branch']], $this->preprocessor->processCondition($cond4));

		$cond5 = ['index.tx >= 5'];
		Assert::same(['index.tx' => ['$gte' => '5']], $this->preprocessor->processCondition($cond5));

		$cond6 = ['index.tx.ax < %i' => '2'];
		Assert::same(['index.tx.ax' => ['$lt' => 2]], $this->preprocessor->processCondition($cond6));

		$cond7 = ['domain' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $this->preprocessor->processCondition($cond7));

		$cond8 = ['domain IN' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $this->preprocessor->processCondition($cond8));

		$cond9 = ['domain NOT_IN' => $testArray2];
		Assert::same(['domain' => ['$nin' => $testArray1]], $this->preprocessor->processCondition($cond9));

		$cond10 = ['domain EXISTS' => TRUE];
		Assert::same(['domain' => ['$exists' => TRUE]], $this->preprocessor->processCondition($cond10));

		$cond11 = ['domain' => ['$exists' => TRUE]];
		Assert::same(['domain' => ['$exists' => TRUE]], $this->preprocessor->processCondition($cond11));

		$cond12 = ['index $lt %i' => '2'];
		Assert::same(['index' => ['$lt' => 2]], $this->preprocessor->processCondition($cond12));

		$cond13 = ['domain $in' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $this->preprocessor->processCondition($cond13));
	}

	function testProcessConditionOr()
	{
		$cond = ['$or' => ['size > 10', 'score < %i' => 20, 'domain EXISTS' => TRUE]];

		Assert::same([
			'$or' => [
				['size' => ['$gt' => '10']],
				['score' => ['$lt' => 20]],
				['domain' => ['$exists' => TRUE]]
			]], $this->preprocessor->processCondition($cond));
	}

	function testProcessConditionElemMatch()
	{
		$results = [
			'results' => [
				'$elemMatch' => [
					'size' => 10,
					'score' => ['$lt' => 20],
					'width' => ['$gt' => '10']
				]
		]];

		$cond1 = ['results ELEM_MATCH' => ['size' => 10, 'score < %i' => '20', 'width > 10']];

		Assert::same($results, $this->preprocessor->processCondition($cond1));

		$cond2 = [
			'results' => [
				'$elemMatch' => [
					'size' => 10,
					'score < %i' => '20',
					'width' => ['$gt' => '10']
				]
			]
		];

		Assert::same($results, $this->preprocessor->processCondition($cond2));
	}

	function testProcessConditionLike()
	{
		$this->driver->shouldReceive('convertToDriver')->once()->with('/test$/i', 're')->andReturn('regexpA');
		$cond1 = ['domain LIKE' => '%test'];
		$regx1 = $this->preprocessor->processCondition($cond1);

		Assert::same($regx1['domain'], 'regexpA');

		$this->driver->shouldReceive('convertToDriver')->once()->with('/^test/i', 're')->andReturn('regexpB');
		$cond2 = ['domain LIKE' => 'test%'];
		$regx2 = $this->preprocessor->processCondition($cond2);

		Assert::same($regx2['domain'], 'regexpB');

		$this->driver->shouldReceive('convertToDriver')->once()->with('/test/i', 're')->andReturn('regexpC');
		$cond3 = ['domain LIKE' => '%test%'];
		$regx3 = $this->preprocessor->processCondition($cond3);

		Assert::same($regx3['domain'], 'regexpC');
	}

	function testProcessConditionStructure()
	{
		$conds1 = [['domain = bus', 'size > %i' => '45'], ['pr_id IN %i[]' => [1, 2]]];

		$conds2 = ['domain = bus', 'size > %i' => '45', 'pr_id IN' => [1, 2]];

		$expected = ['$and' => [
				['domain' => 'bus'],
				['size' => ['$gt' => 45]],
				['pr_id' => ['$in' => [1, 2]]]
		]];

		Assert::same($expected, $this->preprocessor->processCondition($conds1));
		Assert::same($expected, $this->preprocessor->processCondition($conds2));

		$conds3 = ['domain' => 'bus'];
		$conds4 = [['domain' => 'bus']];

		Assert::same($conds3, $this->preprocessor->processCondition($conds3));
		Assert::same($conds3, $this->preprocessor->processCondition($conds4));
	}

	function testProcessConditionIncomplete()
	{
		$cond1 = ['domain = %s'];

		Assert::exception(function() use ($cond1) {
			$this->preprocessor->processCondition($cond1);
		}, 'Mva\Dbm\InvalidArgumentException');

		$cond2 = ['domain'];

		Assert::exception(function() use ($cond2) {
			$this->preprocessor->processCondition($cond2);
		}, 'Mva\Dbm\InvalidArgumentException');
	}

}

$test = new QueryProcessorProcessTest();
$test->run();
