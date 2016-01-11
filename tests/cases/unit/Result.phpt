<?php

namespace Dbm\Tests;

use Mockery,
	Tester\Assert,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Driver\IDriver,
	Mva\Dbm\Result\ResultFactory,
	Mva\Dbm\Result\IResultFactory;

require __DIR__ . "/../../bootstrap.php";

class ResultTest extends UnitTestCase
{

	/** @var IDriver */
	private $driver;

	/** @var IResultFactory */
	private $factory;

	protected function setUp()
	{
		parent::setUp();

		$this->driver = Mockery::mock(IDriver::class);
		$this->factory = new ResultFactory($this->driver);
	}

	function getResultData()
	{
		$row1 = [
			'_id' => '12a2',
			'name' => (object) ['n' => 'Roman'],
			'age' => 27,
			'birth' => (object) ['d' => '10.10.2010'],
			'changed' => 946684958
		];

		$row2 = [
			'_id' => '52a2',
			'name' => (object) ['n' => 'Vendy'],
			'age' => 25,
			'birth' => (object) ['d' => '12.12.2011'],
			'changed' => 946684923
		];

		return [$row1, $row2];
	}

	function getResultDataAggregation()
	{
		return [
			[
				'_id' => ['count' => 4],
				'age' => 25,
				'name' => 'Vendy'
			]
		];
	}

	function testNormalize()
	{
		$data = $this->getResultData();
		$result = $this->factory->create([]);

		$this->driver->shouldReceive('convertToPhp')->once()->with($data[0]['name'])->andReturn('Roman');
		$this->driver->shouldReceive('convertToPhp')->once()->with($data[0]['birth'])->andReturn('10.10.2010');
		$normalized1 = $result->normalizeDocument($data[0]);
		Assert::same(['12a2', 'Roman', 27, '10.10.2010', 946684958], array_values($normalized1));

		$this->driver->shouldReceive('convertToPhp')->once()->with($data[1]['name'])->andReturn('Vendy');
		$this->driver->shouldReceive('convertToPhp')->once()->with($data[1]['birth'])->andReturn('12.12.2011');
		$normalized2 = $result->normalizeDocument($data[1]);
		Assert::same(['52a2', 'Vendy', 25, '12.12.2011', 946684923], array_values($normalized2));
	}

	function testNormalizeAggregation()
	{
		$data = $this->getResultDataAggregation();
		$result = $this->factory->create([]);

		$this->driver->shouldNotReceive('convertToPhp');
		$normalized = $result->normalizeDocument($data[0]);
		Assert::same(['count' => 4, 'age' => 25, 'name' => 'Vendy'], $normalized);
	}

	function testNormalizeRecursive()
	{
		$data = [
			'name' => 'Roman',
			'birth' => (object) ['d' => '12.12.1988'],
			'graduated' => [
				'bc' => (object) ['d' => '13.05.2011'],
				'msc' => (object) ['d' => '06.05.2013']
			]
		];

		$this->driver->shouldReceive('convertToPhp')->once()->with($data['birth'])->andReturn('12.12.1988');
		$this->driver->shouldReceive('convertToPhp')->once()->with($data['graduated']['bc'])->andReturn('13.05.2011');
		$this->driver->shouldReceive('convertToPhp')->once()->with($data['graduated']['msc'])->andReturn('06.05.2013');

		$result = $this->factory->create([]);
		$normalized = $result->normalizeDocument($data);

		Assert::same($normalized['name'], 'Roman');
		Assert::same($normalized['birth'], '12.12.1988');
		Assert::same($normalized['graduated']['bc'], '13.05.2011');
		Assert::same($normalized['graduated']['msc'], '06.05.2013');
	}

	function testFetch()
	{
		$this->driver->shouldReceive('convertToPhp')->times(4)->withAnyArgs()->andReturnNull();

		$result = $this->factory->create($this->getResultData());

		$item1 = $result->fetch();
		Assert::same(27, $item1['age']);

		$item2 = $result->fetch();
		Assert::same(25, $item2['age']);
	}

	function testFetchField()
	{
		$this->driver->shouldReceive('convertToPhp')->times(4)->withAnyArgs()->andReturnNull();

		$result = $this->factory->create($this->getResultData());

		Assert::same('12a2', $result->fetchField());
	}

	function testFetchPairs()
	{
		$this->driver->shouldReceive('convertToPhp')->times(12)->withAnyArgs()->andReturnNull();

		$result = $this->factory->create($this->getResultData());

		$return1 = $result->fetchPairs('_id');
		Assert::same(array_keys($return1), ['12a2', '52a2']);
		Assert::true(is_array($return1['12a2']));
		Assert::true(is_array($return1['52a2']));

		$return2 = $result->fetchPairs(NULL, '_id');
		Assert::same($return2, ['12a2', '52a2']);

		$return3 = $result->fetchPairs('_id', 'age');
		Assert::same(['12a2' => 27, '52a2' => 25], $return3);
	}

	function testFetchAll()
	{
		$this->driver->shouldReceive('convertToPhp')->times(4)->withAnyArgs()->andReturnNull();

		$result = $this->factory->create($this->getResultData());

		$fethed = $result->fetchAll();

		Assert::type('array', $fethed);
		Assert::count(2, $fethed);
	}

	function testGetResult()
	{
		$this->driver->shouldNotReceive('convertToPhp');

		$data = $this->getResultData();
		
		$result = $this->factory->create([$data[0], $data[1]]);

		$return = $result->getRawResult();

		Assert::same($return, [$data[0], $data[1]]);
	}

}

$test = new ResultTest();
$test->run();
