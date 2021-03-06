<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini mongo
 */

namespace Dbm\Tests;

use Tester\Assert,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\DriverTestCase,
	Mva\Dbm\Driver\Mongo\MongoDriver,
	Mva\Dbm\Driver\Mongo\MongoQueryAdapter;

require __DIR__ . "/../../bootstrap.php";

class MongoDriverTest extends DriverTestCase
{

	/** @var MongoDriver */
	private $driver;

	protected function setUp()
	{
		parent::setUp();
		$this->driver = $this->getConnection()->getDriver();
	}

	public function testConvertPhp()
	{
		$item1 = $this->driver->convertToPhp(new \MongoDate('946684923'));
		Assert::true($item1 instanceof \DateTime);

		$item3 = $this->driver->convertToPhp(new \MongoId('51b14c2de8e185801f000006'));
		Assert::same('51b14c2de8e185801f000006', $item3);
	}

	public function testConvertDriver()
	{
		$item1 = $this->driver->convertToDriver('946684923', IDriver::TYPE_DATETIME);
		Assert::true($item1 instanceof \MongoDate);
		Assert::same($item1->sec, 946684923);

		$item2 = $this->driver->convertToDriver(new \DateTime('2000-01-01 01:02:03'), IDriver::TYPE_DATETIME);
		Assert::true($item2 instanceof \MongoDate);
		Assert::same($item2->sec, 946684923);

		$item5 = $this->driver->convertToDriver('51b14c2de8e185801f000006', IDriver::TYPE_OID);
		Assert::true($item5 instanceof \MongoId);
		Assert::same('51b14c2de8e185801f000006', (string) $item5);
	}

	public function testGetResource()
	{
		Assert::true($this->driver->getResource() instanceof \MongoDB);
	}

	public function testGetDatabaseName()
	{
		Assert::same($this->dbname, $this->driver->getDatabaseName());
	}

	public function testGetQueryAdapter()
	{
		Assert::true($this->driver->getQueryAdapter() instanceof MongoQueryAdapter);
	}

}

$test = new MongoDriverTest();
$test->run();
