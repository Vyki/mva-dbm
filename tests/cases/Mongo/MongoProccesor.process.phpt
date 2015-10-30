<?php

namespace Dbm\Tests\Mongo;

use Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Driver;

$database = require __DIR__ . "/../../bootstrap.php";

class MongoProcessor_ConditionsTest extends TestCase
{

	/** @return Driver\Mongo\MongoQueryProcessor */
	function getProcessor()
	{
		return new Driver\Mongo\MongoQueryProcessor();
	}

	function testProcessSelect()
	{
		$pc = $this->getProcessor();

		$select1 = ['name' => TRUE, 'domain' => FALSE, '!item.subitem' => TRUE];

		Assert::same($select1, $pc->processSelect($select1));

		$select2 = ['name', '!domain', '!!item.subitem'];

		Assert::same($select1, $pc->processSelect($select2));
	}

	function testProcessData()
	{
		$pc = $this->getProcessor();

		$actual = $pc->processData([
			'type%s' => 'vehicle',
			'width%i' => '27',
			'height%f' => '34.4',
			'positive%b' => 1,
			'%%message%%s' => 'test',
			'%%message%%%s' => 'test',
			'samples%f[]' => ['1', '3.3', 3.4, 3],
			'_id' => new \MongoId('54ccf5639ab253f598d6b4a5'),
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

		$expdate = $pc->processData(['date' => new \DateTime('2000-01-01 01:02:03')]);

		Assert::true($expdate['date'] instanceof \MongoDate);
		Assert::same($expdate['date']->sec, 946684923);
	}

	function processDataRecursive()
	{
		$pc = $this->getProcessor();

		$actual = $pc->processData([
			'width%i' => '27',
			'height%f' => '34.4',
			'samples' => [
				'one%s' => 12.3,
				'two%i[]' => ['12', 13.3, 5]
			]
		]);

		$expected = $pc->processData([
			'width' => 27,
			'height' => 34.4,
			'samples' => [
				'one' => '12.3',
				'two' => [12, 13, 5]
			]
		]);

		Assert::same($expected, $actual);
	}

	function testProcessUpdate()
	{
		$pc = $this->getProcessor();

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

		Assert::same($expected, $pc->processUpdate($data));
	}

	function testProcessCondition_operators()
	{
		$pc = $this->getProcessor();

		$testArray1 = ['bus', 2, 'branch'];
		$testArray2 = array_combine($testArray1, $testArray1);

		//where test
		$cond1 = ['domain' => 'branch'];
		Assert::same(['domain' => 'branch'], $pc->processCondition($cond1));

		$cond2 = ['domain = %s' => 'branch'];
		Assert::same(['domain' => 'branch'], $pc->processCondition($cond2));

		$cond3 = ['domain = bus'];
		Assert::same(['domain' => 'bus'], $pc->processCondition($cond3));

		$cond4 = ['domain <> %s' => 'branch'];
		Assert::same(['domain' => ['$ne' => 'branch']], $pc->processCondition($cond4));

		$cond5 = ['index.tx >= 5'];
		Assert::same(['index.tx' => ['$gte' => '5']], $pc->processCondition($cond5));

		$cond6 = ['index.tx.ax < %i' => '2'];
		Assert::same(['index.tx.ax' => ['$lt' => 2]], $pc->processCondition($cond6));

		$cond7 = ['domain' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $pc->processCondition($cond7));

		$cond8 = ['domain IN' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $pc->processCondition($cond8));

		$cond9 = ['domain NOT_IN' => $testArray2];
		Assert::same(['domain' => ['$nin' => $testArray1]], $pc->processCondition($cond9));

		$cond10 = ['domain EXISTS' => TRUE];
		Assert::same(['domain' => ['$exists' => TRUE]], $pc->processCondition($cond10));

		$cond11 = ['domain' => ['$exists' => TRUE]];
		Assert::same(['domain' => ['$exists' => TRUE]], $pc->processCondition($cond11));

		$cond12 = ['index $lt %i' => '2'];
		Assert::same(['index' => ['$lt' => 2]], $pc->processCondition($cond12));

		$cond13 = ['domain $in' => $testArray2];
		Assert::same(['domain' => ['$in' => $testArray1]], $pc->processCondition($cond13));
	}

	function testProcessCondition_or()
	{
		$pc = $this->getProcessor();

		$cond = ['$or' => ['size > 10', 'score < %i' => 20, 'domain EXISTS' => TRUE]];

		Assert::same([
			'$or' => [
				['size' => ['$gt' => '10']],
				['score' => ['$lt' => 20]],
				['domain' => ['$exists' => TRUE]]
			]], $pc->processCondition($cond));
	}

	function testProcessCondition_elemMatch()
	{
		$pc = $this->getProcessor();

		$results = [
			'results' => [
				'$elemMatch' => [
					'size' => 10,
					'score' => ['$lt' => 20],
					'width' => ['$gt' => '10']
				]
		]];

		$cond1 = ['results ELEM_MATCH' => ['size' => 10, 'score < %i' => '20', 'width > 10']];

		Assert::same($results, $pc->processCondition($cond1));

		$cond2 = [
			'results' => [
				'$elemMatch' => [
					'size' => 10,
					'score < %i' => '20',
					'width' => ['$gt' => '10']
				]
			]
		];

		Assert::same($results, $pc->processCondition($cond2));
	}

	function testProcessCondition_like()
	{
		$pc = $this->getProcessor();

		$cond1 = ['domain LIKE' => '%test'];
		$regx1 = $pc->processCondition($cond1);

		Assert::true($regx1['domain'] instanceof \MongoRegex);
		Assert::same($regx1['domain']->regex, 'test$');

		$cond2 = ['domain LIKE' => 'test%'];
		$regx2 = $pc->processCondition($cond2);

		Assert::true($regx2['domain'] instanceof \MongoRegex);
		Assert::same($regx2['domain']->regex, '^test');

		$cond3 = ['domain LIKE' => '%test%'];
		$regx3 = $pc->processCondition($cond3);

		Assert::true($regx3['domain'] instanceof \MongoRegex);
		Assert::same($regx3['domain']->regex, 'test');
	}

	function testProcessCondition_structure()
	{
		$pc = $this->getProcessor();

		$conds1 = [['domain = bus', 'size > %i' => '45'], ['pr_id IN %i[]' => [1, 2]]];

		$conds2 = ['domain = bus', 'size > %i' => '45', 'pr_id IN' => [1, 2]];

		$expected = ['$and' => [
				['domain' => 'bus'],
				['size' => ['$gt' => 45]],
				['pr_id' => ['$in' => [1, 2]]]
		]];

		Assert::same($expected, $pc->processCondition($conds1));
		Assert::same($expected, $pc->processCondition($conds2));

		$conds3 = ['domain' => 'bus'];
		$conds4 = [['domain' => 'bus']];

		Assert::same($conds3, $pc->processCondition($conds3));
		Assert::same($conds3, $pc->processCondition($conds4));
	}

	function testIncompleteCondition()
	{
		$pc = $this->getProcessor();

		$cond1 = ['domain = %s'];

		Assert::exception(function() use ($pc, $cond1) {
			$pc->processCondition($cond1);
		}, 'Mva\Dbm\InvalidArgumentException');

		$cond2 = ['domain'];

		Assert::exception(function() use ($pc, $cond2) {
			$pc->processCondition($cond2);
		}, 'Mva\Dbm\InvalidArgumentException');
	}

}

$test = new MongoProcessor_ConditionsTest();
$test->run();
