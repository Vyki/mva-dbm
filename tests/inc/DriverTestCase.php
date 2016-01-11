<?php

namespace Dbm\Tests;

use Tester\TestCase,
	Tester\Environment,
	Mva\Dbm\Connection;

class DriverTestCase extends TestCase
{

	/** @var Connection */
	private $connection;
	
	protected $dbname = 'mva_test';

	public function loadData($collection = 'test')
	{
		Environment::lock("data-$collection", TEMP_DIR);
		exec("mongoimport --db $this->dbname --drop --collection $collection < " . __DIR__ . "/../cases/test.txt");
	}

	protected function createConnection(array $params = [])
	{
		$options = array_merge(['database' => $this->dbname], $params, Environment::loadData());
		return new Connection($options);
	}

	/** @return Connection */
	public function getConnection()
	{
		if ($this->connection === NULL) {
			$this->connection = $this->createConnection();
			$this->connection->connect();
		}

		return $this->connection;
	}

}
