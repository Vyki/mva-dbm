<?php

namespace Dbm\Tests\Mongo;

use DateTime,
	Tester\Assert,
	Mva\Dbm\Driver;

use MongoId,
	MongoDate,
	MongoTimestamp;

require __DIR__ . "/../../bootstrap.php";

function assert_normalize($document)
{
	Assert::true(is_string($document['_id']));
	Assert::true(is_int($document['age']));
	Assert::true($document['birth'] instanceof DateTime);
	Assert::true($document['changed'] instanceof DateTime);
}

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
	'_id' => [
		'count' => 4
	],
	'age' => 25,
	'name' => 'Vendy'
];

// test find result
$data1 = ['roman' => $row1, 'vendy' => $row2];
$result1 = new Driver\Mongo\MongoResult($data1);

// test fetch and normalize
assert_normalize($item1 = $result1->fetch());
Assert::same('Roman', $item1['name']);

assert_normalize($item2 = $result1->fetch());
Assert::same('Vendy', $item2['name']);

// test iterator and normalize
foreach ($result1 as $row) {
	assert_normalize($row);
}

// test fetchAll
foreach ($result1->fetchAll() as $row) {
	assert_normalize($row);
}

// test getResult original data
foreach ($result1->getResult() as $index => $original) {
	Assert::same($data1[$index], $original);
}

// test aggeragation result
$data2 = [$row3];
$result2 = new Driver\Mongo\MongoResult($data2);

Assert::same([
	'age' => 25, 
	'name' => 'Vendy', 
	'count' => 4
], $result2->fetch());

// test fetchField
$data3 = [$row3];
$result3 = new Driver\Mongo\MongoResult($data3);
Assert::same(25, $result3->fetchField());