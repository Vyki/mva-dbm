<?php

namespace Dbm\Tests\Mongo;

use Tester\Assert,
	Mva\Dbm\Driver;

$database = require __DIR__ . "/../../bootstrap.php";

$test = ['bus', 2, 'branch'];

$test2 = array_combine($test, $test);

$a = new Driver\Mongo\MongoQueryBuilder('grid');

//from test
Assert::same('grid', $a->from);

$a->setFrom('grid_test');
Assert::same('grid_test', $a->from);

//where test
$a->addWhere('domain', 'branch');
Assert::same(['domain' => 'branch'], $a->where[0]);

$a->addWhere(['domain = %s' => 'branch', 'domain != bus']);
Assert::same(['domain = %s' => 'branch'], $a->where[1]);
Assert::same([0 => 'domain != bus'], $a->where[2]);


//having test - same as where
$a->addHaving('domain <> %s', 'branch');
Assert::same(['domain <> %s' => 'branch'], $a->having[0]);

$a->addHaving('domain IN', $test);
Assert::same(['domain IN' => $test], $a->having[1]);


//select test
$a->addSelect('domain');
Assert::same(['domain' => TRUE], $a->select);

$a->addSelect('index');
Assert::same(['domain' => TRUE, 'index' => TRUE], $a->select);

$a->addSelect(['domain', 'coord']);
Assert::same(['domain' => TRUE, 'index' => TRUE, 'coord' => TRUE], $a->select);

//unselect test
$a->addUnselect('_id');
Assert::same(['domain' => TRUE, 'index' => TRUE, 'coord' => TRUE,  '_id' => FALSE], $a->select);

$a->addUnselect(['index', 'pr_id']);
Assert::same(['domain' => TRUE, 'index' => FALSE, 'coord' => TRUE, '_id' => FALSE, 'pr_id' => FALSE], $a->select);

//order test
$a->addOrder('domain ASC');
Assert::same(['domain' => 1], $a->order);

$a->addOrder(['index DESC', 'coord ASC']);
Assert::same(['domain' => 1, 'index' => -1, 'coord' => 1], $a->order);

//set group test
$a->setGroup(['coord', 'domain']);
Assert::same(['coord' => '$coord', 'domain' => '$domain'], $a->group);

$a->setGroup('domain');
Assert::same(['domain' => '$domain'], $a->group);

//aggregate
$a->addSelect('MAX(domain) AS domain_max');
Assert::same(['$max' => '$domain'], $a->aggregate['domain_max']);

$a->addSelect('MIN(delta)');
Assert::same(['$min' => '$delta'], $a->aggregate['_delta_min']);

$a->addAggregate('sum', 'domain', 'dom_sum');
Assert::same(['$sum' => '$domain'], $a->aggregate['dom_sum']);

$a->addAggregate('sum', '*', 'count');
Assert::same(['$sum' => 1], $a->aggregate['count']);

Assert::same(['_id', 'domain_max', '_delta_min', 'dom_sum', 'count'], array_keys($a->aggregate));


$b = new Driver\Mongo\MongoQueryBuilder();

//select distinct
$b->addSelect('DISTINCT domain');
Assert::same(['distinct' => 'domain'], $b->getSelect());

$b->addSelect('coord');
Assert::same(['coord' => TRUE], $b->getSelect());