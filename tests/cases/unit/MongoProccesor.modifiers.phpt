<?php

namespace Dbm\Tests;

use Mockery,
	Tester\Assert,
	Mva\Dbm\Platform,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Platform\Mongo\MongoQueryProcessor;

require __DIR__ . "/../../bootstrap.php";

class MongoProcessorModifiersTest extends UnitTestCase
{

	/** @var IDriver */
	private $driver;

	/** @var MongoQueryProcessor */
	private $preprocessor;

	function setUp()
	{
		parent::setUp();

		$this->driver = Mockery::mock(IDriver::class);
		$this->preprocessor = new MongoQueryProcessor($this->driver);
	}

	function testProcessModifierString()
	{
		Assert::same('domain', $this->preprocessor->processModifier('s', 'domain'));
		Assert::same('1928', $this->preprocessor->processModifier('s', 1928));
		Assert::same('2000-01-01 01:02:03', $this->preprocessor->processModifier('s', new \DateTime('2000-01-01 01:02:03')));
		Assert::same('19.8', $this->preprocessor->processModifier('s', 19.8));
		Assert::same('0.2', $this->preprocessor->processModifier('s', .2));
		Assert::same('1.0E-5', $this->preprocessor->processModifier('s', 1E-5));
	}

	function testProcessModifierInt()
	{
		Assert::same(2, $this->preprocessor->processModifier('i', 2));
		Assert::same(2, $this->preprocessor->processModifier('i', '2'));
		Assert::same(2, $this->preprocessor->processModifier('i', 2.6));
		Assert::same(2, $this->preprocessor->processModifier('i', '2.6'));
		Assert::same(9000, $this->preprocessor->processModifier('i', 9E3));
		Assert::same(9000, $this->preprocessor->processModifier('i', '9E3'));
		Assert::same(0, $this->preprocessor->processModifier('i', 9E-3));
		Assert::same(0, $this->preprocessor->processModifier('i', '9E-3'));
		Assert::same(0, $this->preprocessor->processModifier('i', 'ds'));
		Assert::same(946684923, $this->preprocessor->processModifier('i', new \DateTime('2000-01-01 01:02:03')));
	}

	function testProcessModifierFloat()
	{
		Assert::same(2.0, $this->preprocessor->processModifier('f', 2));
		Assert::same(2.0, $this->preprocessor->processModifier('f', '2'));
		Assert::same(2.6, $this->preprocessor->processModifier('f', 2.6));
		Assert::same(2.6, $this->preprocessor->processModifier('f', '2.6'));
		Assert::same(9000.0, $this->preprocessor->processModifier('f', 9E3));
		Assert::same(9000.0, $this->preprocessor->processModifier('f', '9E3'));
		Assert::same(0.009, $this->preprocessor->processModifier('f', 9E-3));
		Assert::same(0.009, $this->preprocessor->processModifier('f', '9E-3'));
		Assert::same(0.0, $this->preprocessor->processModifier('f', 'ds'));
		Assert::same(946684923.0, $this->preprocessor->processModifier('f', new \DateTime('2000-01-01 01:02:03')));
	}

	function testProcessModifierBool()
	{
		Assert::same(TRUE, $this->preprocessor->processModifier('b', TRUE));
		Assert::same(TRUE, $this->preprocessor->processModifier('b', 'true'));
		Assert::same(FALSE, $this->preprocessor->processModifier('b', 'FALSE'));
		Assert::same(TRUE, $this->preprocessor->processModifier('b', 1));
		Assert::same(FALSE, $this->preprocessor->processModifier('b', 0));
		Assert::same(TRUE, $this->preprocessor->processModifier('b', '1'));
		Assert::same(FALSE, $this->preprocessor->processModifier('b', '0'));
		Assert::same(TRUE, $this->preprocessor->processModifier('b', '1.0'));
		Assert::same(TRUE, $this->preprocessor->processModifier('b', '0.1'));
	}

	function testProcessModifierDateTime()
	{
		$return = (object) ['sec' => 946684923];

		$this->driver->shouldReceive('convertToDriver')->times(4)->with(946684923, 'dt')->andReturn($return);

		$date1 = $this->preprocessor->processModifier('dt', new \DateTime('2000-01-01 01:02:03'));
		Assert::same($return, $date1);

		$date2 = $this->preprocessor->processModifier('dt', '946684923');
		Assert::same($return, $date2);

		$date3 = $this->preprocessor->processModifier('dt', 946684923);
		Assert::same($return, $date3);

		$date4 = $this->preprocessor->processModifier('dt', 946684923.0);
		Assert::same($return, $date4);
	}

	function testProcessModifierTimeStamp()
	{
		$return = (object) ['sec' => 946684923];

		$this->driver->shouldReceive('convertToDriver')->times(4)->with(946684923, 'ts')->andReturn($return);

		$ts1 = $this->preprocessor->processModifier('ts', new \DateTime('2000-01-01 01:02:03'));
		Assert::same($return, $ts1);

		$ts2 = $this->preprocessor->processModifier('ts', '946684923');
		Assert::same($return, $ts2);

		$ts3 = $this->preprocessor->processModifier('ts', 946684923);
		Assert::same($return, $ts3);

		$ts4 = $this->preprocessor->processModifier('ts', 946684923.0);
		Assert::same($return, $ts4);
	}

	function testProcessModifierArray()
	{
		$array1 = $this->preprocessor->processModifier('i[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same([1, 2, 2, 4, 100, 1000], $array1);

		$array2 = $this->preprocessor->processModifier('f[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same([1.0, 2.0, 2.3, 4.3, 100.0, 1000.0], $array2);

		$array3 = $this->preprocessor->processModifier('s[]', [1, '2', '2.3', 4.3, 1E2, '1E3']);
		Assert::same(['1', '2', '2.3', '4.3', '100', '1E3'], $array3);

		$array4 = $this->preprocessor->processModifier('b[]', [TRUE, FALSE, 'TRUE', 'FALSE', 1, 0, 'a']);
		Assert::same([TRUE, FALSE, TRUE, FALSE, TRUE, FALSE, TRUE], $array4);
	}

	function testProcessModifierRegex()
	{
		$reobject = new \stdClass();

		$this->driver->shouldReceive('convertToDriver')->once()->with('/^A/i', 're')->andReturn($reobject);
		$re1 = $this->preprocessor->processModifier('re', '/^A/i');
		Assert::same($re1, $reobject);
	}

	function testProcessModifierOid()
	{
		$oidbject = new \stdClass();

		$this->driver->shouldReceive('convertToDriver')->once()->with('51b14c2de8e185801f000006', 'oid')->andReturn($oidbject);
		$oid = $this->preprocessor->processModifier('oid', '51b14c2de8e185801f000006');
		Assert::same($oid, $oidbject);
	}

}

$test = new MongoProcessorModifiersTest();
$test->run();
