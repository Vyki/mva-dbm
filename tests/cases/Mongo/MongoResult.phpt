<?php

namespace Dbm\Tests\Mongo;

use DateTime,
	Tester\Assert,
	Mva\Dbm\Driver,
	Tester\TestCase;
use MongoId,
	MongoDate,
	MongoTimestamp;

require __DIR__ . "/../../bootstrap.php";

class MongoResultTest extends TestCase
{

	function assertNormalize($document)
	{
		Assert::true(is_string($document['_id']));
		Assert::true(is_int($document['age']));
		Assert::true($document['birth'] instanceof DateTime);
		Assert::true($document['changed'] instanceof DateTime);
	}

	function getResultData()
	{
		$row1 = [
			'_id' => new MongoId(),
			'name' => 'Roman',
			'age' => 27,
			'birth' => new MongoDate(),
			'changed' => new MongoTimestamp()
		];

		$row2 = [
			'_id' => new MongoId(),
			'name' => 'Vendy',
			'age' => 25,
			'birth' => new MongoDate(),
			'changed' => new MongoTimestamp()
		];

		$row3 = [
			'_id' => ['count' => 4],
			'age' => 25,
			'name' => 'Vendy'
		];

		return [$row1, $row2, $row3];
	}

	function testNormalize()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult([]);

		$normalized = $result->normalizeDocument($data[0]);
		$this->assertNormalize($normalized);

		$normalizedAggr = $result->normalizeDocument($data[2]);
		$this->assertNormalize($normalized);

		Assert::same([
			'age' => 25,
			'name' => 'Vendy',
			'count' => 4
		], $normalizedAggr);
	}

	function testFetch()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult(['roman' => $data[0], 'vendy' => $data[1]]);

		$this->assertNormalize($item1 = $result->fetch());
		Assert::same('Roman', $item1['name']);

		$this->assertNormalize($item2 = $result->fetch());
		Assert::same('Vendy', $item2['name']);
	}

	function testFetchField()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult([$data[2]]);

		Assert::same(25, $result->fetchField());
	}

	function testFetchAll()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult(['roman' => $data[0], 'vendy' => $data[1]]);

		$fethed = $result->fetchAll();

		Assert::type('array', $fethed);

		foreach ($fethed as $row) {
			$this->assertNormalize($row);
		}
	}

	function testGetIterator()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult(['roman' => $data[0], 'vendy' => $data[1]]);

		foreach ($result as $row) {
			$this->assertNormalize($row);
		}
	}

	function testGetResult()
	{
		$data = $this->getResultData();
		$result = new Driver\Mongo\MongoResult([$data[0], $data[1]]);

		foreach ($result->getResult() as $index => $original) {
			Assert::same($data[$index], $original);
		}
	}

}

$test = new MongoResultTest();
$test->run();
