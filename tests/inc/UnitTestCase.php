<?php

namespace Dbm\Tests;

use Mockery,
	Tester\TestCase;

class UnitTestCase extends TestCase
{

	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}
