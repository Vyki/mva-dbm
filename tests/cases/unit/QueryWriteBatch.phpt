<?php

namespace Dbm\Tests\Driver\Mongodb;

use Mockery,
	Tester\Assert,
	Dbm\Tests\UnitTestCase,
	Mva\Dbm\Query\QueryProcessor,
	Mva\Dbm\Query\QueryWriteBatch;

$connection = include __DIR__ . "/../../bootstrap.php";

class QueryWriteBatchTest extends UnitTestCase
{

	/** @var QueryWriteBatch */
	private $batch;

	/** @var Mockery\MockInterface */
	private $preprocessor;

	protected function setUp()
	{
		$this->preprocessor = Mockery::mock(QueryProcessor::class);
		$this->batch = new QueryWriteBatch($this->preprocessor);
	}

	public function testInsert()
	{
		$ins = [
			'pr_id%i' => '4',
			'name%s' => 'Test 8',
			'domain%s' => 'beta',
			'size%i' => '101',
			'points%i[]' => ['18', 31, 64.01],
			'type%i' => 10.1
		];

		$ret = [
			'pr_id' => 4,
			'name' => 'Test 8',
			'domain' => 'beta',
			'size' => 101,
			'points' => [18, 31, 64],
			'type' => 10
		];

		$this->preprocessor->shouldReceive('processData')->with($ins, TRUE)->twice()->andReturn($ret);

		$this->batch->insert($ins);
		$this->batch->insert($ins);

		Assert::same([$ret, $ret], $this->batch->getQueue('insert'));
	}

	public function testUpdate()
	{
		$cona = ['pr_id > %i' => '2'];
		$upda = ['name%s' => 'Test 200', '$rename' => ['points' => 'coords']];
		$rupa = ['$rename' => ['points' => 'coords'], '$set' => ['name' => 'Test 200']];
		$rcoa = ['pr_id' => ['$gt' => 2]];

		$conb = ['domain' => 'beta'];
		$updb = ['domain%s' => 'Test 400', '$unset' => ['type']];
		$rupb = ['$unset' => ['type' => ''], '$set' => ['domain' => 'Test 400']];
		$rcob = ['domain' => 'beta'];

		$this->preprocessor->shouldReceive('processUpdate')->with($upda)->once()->andReturn($rupa);
		$this->preprocessor->shouldReceive('processCondition')->with($cona)->once()->andReturn($rcoa);

		$this->batch->update($upda, $cona);

		$this->preprocessor->shouldReceive('processUpdate')->with($updb)->once()->andReturn($rupb);
		$this->preprocessor->shouldReceive('processCondition')->with($conb)->once()->andReturn($rcob);

		$this->batch->update($updb, $conb, TRUE, FALSE);

		$expa = [$rcoa, $rupa, ['upsert' => FALSE, 'multi' => TRUE]];
		$expb = [$rcob, $rupb, ['upsert' => TRUE, 'multi' => FALSE]];

		Assert::same([$expa, $expb], $this->batch->getQueue('update'));
	}

	public function testDelete()
	{
		$cona = ['pr_id > %i' => 2, 'domain = %s' => 'beta'];
		$rcoa = ['$and' => [['pr_id' => ['$gt' => 2]], ['domain' => 'beta']]];

		$conb = ['pr_id <= %i' => 10, 'domain' => ['beta', 'alpha']];
		$rcob = ['$and' => [['pr_id' => ['$gt' => 2]], ['domain' => 'beta']]];

		$this->preprocessor->shouldReceive('processCondition')->with($cona)->once()->andReturn($rcoa);

		$this->batch->delete($cona, TRUE);

		$this->preprocessor->shouldReceive('processCondition')->with($conb)->once()->andReturn($rcob);

		$this->batch->delete($conb, FALSE);

		$expa = [$rcoa, ['limit' => 0]];

		$expb = [$rcob, ['limit' => 1]];

		Assert::same([$expa, $expb], $this->batch->getQueue('delete'));
	}

}

$test = new QueryWriteBatchTest();
$test->run();
