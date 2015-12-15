<?php

/**
 * @testCase
 * @dataProvider? ../../drivers.ini mongo
 */

namespace Dbm\Tests;

use Tester\Assert,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\DriverTestCase;

require __DIR__ . "/../../bootstrap.php";

class MongoDriverTest extends DriverTestCase
{

	public function testConvertPhp()
	{
		$driver = $this->getConnection()->getDriver();

		$item1 = $driver->convertToPhp(new \MongoDate('946684923'));
		Assert::true($item1 instanceof \DateTime);

		$item2 = $driver->convertToPhp(new \MongoTimestamp('946684923'));
		Assert::true($item2 instanceof \DateTime);

		$item3 = $driver->convertToPhp(new \MongoId('51b14c2de8e185801f000006'));
		Assert::same('51b14c2de8e185801f000006', $item3);
	}

	public function testConvertDriver()
	{
		$driver = $this->getConnection()->getDriver();

		$item1 = $driver->convertToDriver('946684923', IDriver::TYPE_DATETIME);
		Assert::true($item1 instanceof \MongoDate);
		Assert::same($item1->sec, 946684923);

		$item2 = $driver->convertToDriver(new \DateTime('2000-01-01 01:02:03'), IDriver::TYPE_DATETIME);
		Assert::true($item2 instanceof \MongoDate);
		Assert::same($item2->sec, 946684923);

		$item3 = $driver->convertToDriver('946684923', IDriver::TYPE_TIMESTAMP);
		Assert::true($item3 instanceof \MongoTimestamp);
		Assert::same($item3->sec, 946684923);

		$item4 = $driver->convertToDriver(new \DateTime('2000-01-01 01:02:03'), IDriver::TYPE_TIMESTAMP);
		Assert::true($item4 instanceof \MongoTimestamp);
		Assert::same($item4->sec, 946684923);

		$item5 = $driver->convertToDriver('51b14c2de8e185801f000006', IDriver::TYPE_OID);
		Assert::true($item5 instanceof \MongoId);
		Assert::same('51b14c2de8e185801f000006', (string) $item5);
	}

	public function testGetResource()
	{
		$driver = $this->getConnection()->getDriver();
		Assert::true($driver->getResource() instanceof \MongoDB);
	}

}

$test = new MongoDriverTest();
$test->run();
