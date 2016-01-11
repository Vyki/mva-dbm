<?php

namespace Dbm\Tests;

use Mockery,
	Tester\Assert,
	Mva\Dbm\Query\Query,
	Mva\Dbm\Query\IQuery,
	Mva\Dbm\Driver\IDriver,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Result\IResult,
	Mva\Dbm\Query\IWriteBatch,
	Mva\Dbm\Query\QueryProcessor,
	Mva\Dbm\Result\IResultFactory,
	Mva\Dbm\Query\QueryWriteBatch,
	Mva\Dbm\Result\ResultWriteBatch;

require __DIR__ . "/../../bootstrap.php";

class QueryTest extends UnitTestCase
{

	/** @var Query */
	private $query;

	/** @var Mockery\MockInterface */
	private $driver;

	/** @var Mockery\MockInterface */
	private $queryAdapter;

	/** @var Mockery\MockInterface */
	private $preprocessor;

	/** @var Mockery\MockInterface */
	private $resultFactory;

	/** @var string */
	private $collectionName = 'test_colection';

	protected function setUp()
	{
		parent::setUp();

		$this->queryAdapter = Mockery::mock(IQuery::class);
		$this->preprocessor = Mockery::mock(QueryProcessor::class);
		$this->resultFactory = Mockery::mock(IResultFactory::class);

		$this->driver = Mockery::mock(IDriver::class);
		$this->driver->shouldReceive('getQueryAdapter')->andReturn($this->queryAdapter);

		$this->query = new Query($this->driver, $this->preprocessor);
		$this->query->setResultfactory($this->resultFactory);
	}

	function testFind()
	{
		$selorig = ['a', '!b'];
		$seltrans = ['a' => TRUE, 'b' => FALSE];

		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$resadapt = [['a' => '11'], ['a' => '12']];
		$resquery = Mockery::mock(IResult::class);

		$optorig = [IQuery::LIMIT => 10, IQuery::OFFSET => 5, IQuery::ORDER => ['a ASC']];
		$opttrans = [IQuery::LIMIT => 10, IQuery::OFFSET => 5, IQuery::ORDER => ['a' => 1]];

		$this->preprocessor->shouldReceive('processSelect')->with($selorig)->andReturn($seltrans);
		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);
		$this->preprocessor->shouldReceive('processOrder')->with(['a ASC'])->andReturn(['a' => 1]);

		$this->queryAdapter->shouldReceive('find')->with($this->collectionName, $seltrans, $condtrans, $opttrans)->andReturn($resadapt);

		$this->resultFactory->shouldReceive('create')->with($resadapt)->andReturn($resquery);

		$return = $this->query->find($this->collectionName, $selorig, $condorig, $optorig);

		Assert::same($resquery, $return);
	}

	function testCount()
	{
		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$resadapt = '10';
		$resquery = 10;

		$opt = [IQuery::LIMIT => 10, IQuery::OFFSET => 5];

		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);

		$this->queryAdapter->shouldReceive('count')->with($this->collectionName, $condtrans, $opt)->andReturn($resadapt);

		$return = $this->query->count($this->collectionName, $condorig, $opt);

		Assert::same($resquery, $return);
	}

	function testDisinct()
	{
		$item = 'a';

		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$resadapt = ['abc', 'cab'];
		$restrans = [[$item => 'abc'], [$item => 'cab']];
		$resquery = Mockery::mock(IResult::class);

		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);

		$this->queryAdapter->shouldReceive('distinct')->with($this->collectionName, $item, $condtrans)->andReturn($resadapt);

		$this->resultFactory->shouldReceive('create')->with($restrans)->andReturn($resquery);

		$return = $this->query->distinct($this->collectionName, $item, $condorig);

		Assert::same($resquery, $return);
	}

	function testDelete()
	{
		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$resadapt = '10';
		$resquery = 10;

		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);

		$this->queryAdapter->shouldReceive('delete')->with($this->collectionName, $condtrans, FALSE)->andReturn($resadapt);

		$return = $this->query->delete($this->collectionName, $condorig, FALSE);

		Assert::same($resquery, $return);
	}

	function testInsert()
	{
		$dataorig = [
			'id%i' => '2',
			'name' => 'Test',
			'points%f[]' => ['18.0', 31.32],
		];

		$datatrans = [
			'id' => 2,
			'name' => 'Test',
			'points' => [18.0, 31.32],
		];

		$resadapt = array_merge(['_id' => 'ab2c3d'], $datatrans);
		$resquery = Mockery::mock(IResult::class);

		$this->preprocessor->shouldReceive('processData')->with($dataorig, TRUE)->andReturn($datatrans);

		$this->queryAdapter->shouldReceive('insert')->with($this->collectionName, $datatrans)->andReturn($resadapt);

		$this->resultFactory->shouldReceive('create')->with([$resadapt])->andReturn($resquery);

		$resquery->shouldReceive('fetch')->withNoArgs()->andReturn($resadapt);

		$return = $this->query->insert($this->collectionName, $dataorig);

		Assert::same($resadapt, $return);
	}

	function testUpdateUpdate()
	{
		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$dataorig = [
			'id%i' => '2',
			'name' => 'Test',
			'points%f[]' => ['18.0', 31.32],
		];

		$datatrans = [
			'id' => 2,
			'name' => 'Test',
			'points' => [18.0, 31.32],
		];

		$resadapt = '10';
		$resquery = 10;

		$this->preprocessor->shouldReceive('processUpdate')->with($dataorig)->andReturn($datatrans);
		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);

		$this->queryAdapter->shouldReceive('update')->with($this->collectionName, $datatrans, $condtrans, FALSE, TRUE)->andReturn($resadapt);

		$return = $this->query->update($this->collectionName, $dataorig, $condorig, FALSE);

		Assert::same($resquery, $return);
	}

	function testUpdateUpsert()
	{
		$condorig = ['a > %i' => '10'];
		$condtrans = ['a' => ['$gt' => 10]];

		$dataorig = [
			'$set' => [
				'id%i' => '2',
				'name' => 'Test',
				'points%f[]' => ['18.0', 31.32],
			]
		];

		$datatrans = [
			'$set' => [
				'id' => 2,
				'name' => 'Test',
				'points' => [18.0, 31.32],
			]
		];

		$resadapt = ['_id' => 'ab2c3d'];
		$restrans = $resadapt + $datatrans['$set'];
		$resquery = Mockery::mock(IResult::class);

		$this->preprocessor->shouldReceive('formatCmd')->with('set')->andReturn('$set');
		$this->preprocessor->shouldReceive('processUpdate')->with($dataorig)->andReturn($datatrans);
		$this->preprocessor->shouldReceive('processCondition')->with($condorig)->andReturn($condtrans);

		$this->queryAdapter->shouldReceive('update')->with($this->collectionName, $datatrans, $condtrans, TRUE, FALSE)->andReturn($restrans);

		$this->resultFactory->shouldReceive('create')->with([$restrans])->andReturn($resquery);

		$resquery->shouldReceive('fetch')->withNoArgs()->andReturn($restrans);

		$return = $this->query->update($this->collectionName, $dataorig, $condorig, TRUE, FALSE);

		Assert::same($restrans, $return);
	}

	function testWriteBatch()
	{
		$queue = Mockery::mock(QueryWriteBatch::class);
		$writer = Mockery::mock(IWriteBatch::class);

		$return = [
			ResultWriteBatch::UPDATED => 1,
			ResultWriteBatch::MATCHED => 3,
			ResultWriteBatch::DELETED => 2,
			ResultWriteBatch::INSERTED => 4,
			ResultWriteBatch::UPSERTED => 5,
			ResultWriteBatch::INSERTED_IDS => ['1abdc'],
			ResultWriteBatch::UPSERTED_IDS => ['3abdc']
		];

		$writer->shouldReceive('write')->with($this->collectionName, $queue)->once()->andReturn($return);

		$this->driver->shouldReceive('getWriteBatch')->withNoArgs()->once()->andReturn($writer);
		$this->driver->shouldReceive('convertToPhp')->with('1abdc')->once()->andReturn('1abdc');
		$this->driver->shouldReceive('convertToPhp')->with('3abdc')->once()->andReturn('3abdc');

		$result = $this->query->writeBatch($this->collectionName, $queue);

		Assert::same(['modified' => 1, 'inserted' => 4, 'matched' => 3, 'upserted' => 5, 'removed' => 2], $result->getStats());
		Assert::same(['1abdc'], $result->getInsertedIds());
		Assert::same(['3abdc'], $result->getUpsertedIds());
	}

}

$test = new QueryTest();
$test->run();
