<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini mongodb
 */

namespace Dbm\Tests;

use MongoDB,
	Tester\Assert,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\DriverTestCase,
	Mva\Dbm\Driver\Mongo\MongodbDriver,
	Mva\Dbm\Driver\Mongodb\MongodbQueryAdapter;

require __DIR__ . "/../../bootstrap.php";

class MongodbDriverTest extends DriverTestCase
{

	/** @var MongodbDriver */
	private $driver;

	protected function setUp()
	{
		parent::setUp();
		$this->driver = $this->getConnection()->getDriver();
	}

	public function testConvertPhp()
	{
		$item1 = $this->driver->convertToPhp(new MongoDB\BSON\UTCDatetime('946684923'));
		Assert::true($item1 instanceof \DateTime);

		$item3 = $this->driver->convertToPhp(new MongoDB\BSON\ObjectID('51b14c2de8e185801f000006'));
		Assert::same('51b14c2de8e185801f000006', $item3);
	}

	public function testConvertDriver()
	{
		$item1 = $this->driver->convertToDriver('946684923', IDriver::TYPE_DATETIME);
		Assert::true($item1 instanceof MongoDB\BSON\UTCDatetime);
		Assert::same((string) $item1, '946684923');

		$item2 = $this->driver->convertToDriver(new \DateTime('2000-01-01 01:02:03'), IDriver::TYPE_DATETIME);
		Assert::true($item2 instanceof MongoDB\BSON\UTCDatetime);
		Assert::same((string) $item2, '946684923');

		$item5 = $this->driver->convertToDriver('51b14c2de8e185801f000006', IDriver::TYPE_OID);
		Assert::true($item5 instanceof MongoDB\BSON\ObjectID);
		Assert::same((string) $item5, '51b14c2de8e185801f000006');
	}

	public function testGetResource()
	{
		Assert::true($this->driver->getResource() instanceof MongoDB\Driver\Manager);
	}

	public function testGetDatabaseName()
	{
		Assert::same($this->dbname, $this->driver->getDatabaseName());
	}

	public function testGetQueryAdapter()
	{
		Assert::true($this->driver->getQueryAdapter() instanceof MongodbQueryAdapter);
	}

}

$test = new MongodbDriverTest();
$test->run();
