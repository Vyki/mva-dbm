<?php

namespace Dbm\Tests\Mongo;

use Tester\Assert,
	Tester\TestCase,
	Mva\Dbm\Driver;

$database = require __DIR__ . "/../../bootstrap.php";

class MongoProcessor_ModifiersTest extends TestCase
{

	/** @return Driver\Mongo\MongoQueryProcessor */
	function getProcessor()
	{
		return new Driver\Mongo\MongoQueryProcessor();
	}

	function testProcessModifierString()
	{
		$pc = $this->getProcessor();

		Assert::same('domain', $pc->processModifier('s', 'domain'));
		Assert::same('1928', $pc->processModifier('s', 1928));
		Assert::same('2000-01-01 01:02:03', $pc->processModifier('s', new \DateTime('2000-01-01 01:02:03')));
		Assert::same('19.8', $pc->processModifier('s', 19.8));
		Assert::same('0.2', $pc->processModifier('s', .2));
		Assert::same('1.0E-5', $pc->processModifier('s', 1E-5));
		Assert::same('51b14c2de8e185801f000006', $pc->processModifier('s', new \MongoId('51b14c2de8e185801f000006')));
	}

	function testProcessModifierInt()
	{
		$pc = $this->getProcessor();

		Assert::same(2, $pc->processModifier('i', 2));
		Assert::same(2, $pc->processModifier('i', '2'));
		Assert::same(2, $pc->processModifier('i', 2.6));
		Assert::same(2, $pc->processModifier('i', '2.6'));
		Assert::same(9000, $pc->processModifier('i', 9E3));
		Assert::same(9000, $pc->processModifier('i', '9E3'));
		Assert::same(0, $pc->processModifier('i', 9E-3));
		Assert::same(0, $pc->processModifier('i', '9E-3'));
		Assert::same(0, $pc->processModifier('i', 'ds'));
		Assert::same(946684923, $pc->processModifier('i', new \DateTime('2000-01-01 01:02:03')));
	}

	function testProcessModifierFloat()
	{
		$pc = $this->getProcessor();

		Assert::same(2.0, $pc->processModifier('f', 2));
		Assert::same(2.0, $pc->processModifier('f', '2'));
		Assert::same(2.6, $pc->processModifier('f', 2.6));
		Assert::same(2.6, $pc->processModifier('f', '2.6'));
		Assert::same(9000.0, $pc->processModifier('f', 9E3));
		Assert::same(9000.0, $pc->processModifier('f', '9E3'));
		Assert::same(0.009, $pc->processModifier('f', 9E-3));
		Assert::same(0.009, $pc->processModifier('f', '9E-3'));
		Assert::same(0.0, $pc->processModifier('f', 'ds'));
		Assert::same(946684923.0, $pc->processModifier('f', new \DateTime('2000-01-01 01:02:03')));
	}

	function testProcessModifierBool()
	{
		$pc = $this->getProcessor();

		Assert::same(TRUE, $pc->processModifier('b', TRUE));
		Assert::same(TRUE, $pc->processModifier('b', 'true'));
		Assert::same(FALSE, $pc->processModifier('b', 'FALSE'));
		Assert::same(TRUE, $pc->processModifier('b', 1));
		Assert::same(FALSE, $pc->processModifier('b', 0));
		Assert::same(TRUE, $pc->processModifier('b', '1'));
		Assert::same(FALSE, $pc->processModifier('b', '0'));
		Assert::same(TRUE, $pc->processModifier('b', '1.0'));
		Assert::same(TRUE, $pc->processModifier('b', '0.1'));
	}

	function testProcessModifierDateTime()
	{
		$pc = $this->getProcessor();

		$date1 = $pc->processModifier('dt', new \DateTime('2000-01-01 01:02:03'));
		Assert::true($date1 instanceof \MongoDate);
		Assert::same(946684923, $date1->sec);

		$date2 = $pc->processModifier('dt', '946684923');
		Assert::true($date2 instanceof \MongoDate);
		Assert::same(946684923, $date2->sec);

		$date3 = $pc->processModifier('dt', 946684923);
		Assert::true($date3 instanceof \MongoDate);
		Assert::same(946684923, $date3->sec);

		$date4 = $pc->processModifier('dt', 946684923.0);
		Assert::true($date3 instanceof \MongoDate);
		Assert::same(946684923, $date4->sec);
	}

	function testProcessModifierTimeStamp()
	{
		$pc = $this->getProcessor();

		$ts1 = $pc->processModifier('ts', new \DateTime('2000-01-01 01:02:03'));
		Assert::true($ts1 instanceof \MongoTimestamp);
		Assert::same(946684923, $ts1->sec);

		$ts2 = $pc->processModifier('ts', '946684923');
		Assert::true($ts2 instanceof \MongoTimestamp);
		Assert::same(946684923, $ts2->sec);

		$ts3 = $pc->processModifier('ts', 946684923);
		Assert::true($ts3 instanceof \MongoTimestamp);
		Assert::same(946684923, $ts3->sec);

		$ts4 = $pc->processModifier('ts', 946684923.0);
		Assert::true($ts4 instanceof \MongoTimestamp);
		Assert::same(946684923, $ts4->sec);
	}

	function testProcessModifierArray()
	{
		$pc = $this->getProcessor();

		$array1 = $pc->processModifier('i[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same([1, 2, 2, 4, 100, 1000], $array1);

		$array2 = $pc->processModifier('f[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same([1.0, 2.0, 2.3, 4.3, 100.0, 1000.0], $array2);

		$array3 = $pc->processModifier('s[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same(['1', '2', '2.3', '4.3', '100', '1E3'], $array3);

		$array4 = $pc->processModifier('b[]', [TRUE, FALSE, 'TRUE', 'FALSE', 1, 0, 'a']);
		Assert::same([TRUE, FALSE, TRUE, FALSE, TRUE, FALSE, TRUE], $array4);
	}

	function testProcessModifierRegex()
	{
		$pc = $this->getProcessor();

		$re1 = $pc->processModifier('re', '/^A/i');
		Assert::true($re1 instanceof \MongoRegex);
		Assert::same($re1->regex, '^A');
		Assert::same($re1->flags, 'i');

		$re2 = $pc->processModifier('re', new \MongoRegex('/^A/i'));
		Assert::true($re2 instanceof \MongoRegex);
		Assert::same($re2->regex, '^A');
		Assert::same($re2->flags, 'i');
	}

	function testProcessModifierOther()
	{
		$pc = $this->getProcessor();

		$oid = $pc->processModifier('oid', '51b14c2de8e185801f000006');
		Assert::true($oid instanceof \MongoId);
		Assert::same((string) $oid, '51b14c2de8e185801f000006');

		$tostring = $pc->processModifier('any', new \MongoId('51b14c2de8e185801f000006'));
		Assert::same($tostring, '51b14c2de8e185801f000006');
	}

}

$test = new MongoProcessor_ModifiersTest();
$test->run();
