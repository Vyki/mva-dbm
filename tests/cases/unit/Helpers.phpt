<?php

namespace Dbm\Mongo;

use Tester\Assert,
	Mva\Dbm\Helpers,
	Dbm\Tests\UnitTestCase;

require __DIR__ . "/../../bootstrap.php";

class HelpersTest extends UnitTestCase
{

	function testContract()
	{
		$data = [
			'name' => 'NY',
			'borough' => 'Queens',
			'address' => [
				'street' => 'Astoria Boulevard',
				'number' => '8825',
				'coord' => [-73.8803, 40.7643]
			]
		];

		Assert::same([
			'name' => 'NY',
			'borough' => 'Queens',
			'address.street' => 'Astoria Boulevard',
			'address.number' => '8825',
			'address.coord' => [-73.8803, 40.7643]], Helpers::contractArray($data));

		$expected = [
			'name' => 'NY',
			'borough' => 'Queens',
			'address.street' => 'Astoria Boulevard',
			'address.number' => '8825',
			'address.coord.0' => -73.8803,
			'address.coord.1' => 40.7643
		];

		Assert::same(Helpers::contractArray($data, '.', TRUE), $expected);

		Assert::same(Helpers::contractArray($data, TRUE), $expected);
	}

	function testExpand()
	{
		$data = [
			'name' => 'NY',
			'borough' => 'Queens',
			'address.street' => 'Astoria Boulevard',
			'address.number' => '8825',
			'address.coord.0' => -73.8803,
			'address.coord.1' => 40.7643
		];

		Assert::same([
			'name' => 'NY',
			'borough' => 'Queens',
			'address' => [
				'street' => 'Astoria Boulevard',
				'number' => '8825',
				'coord' => [-73.8803, 40.7643]
			]], Helpers::expandArray($data));
	}

}

$test = new HelpersTest();
$test->run();
