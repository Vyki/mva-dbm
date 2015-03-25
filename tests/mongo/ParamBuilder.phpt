<?php

namespace Test;

use Mva,
	Tester\Assert;

$database = require __DIR__ . "/../bootstrap.php";

$test = ['bus', 2, 'branch'];

$test2 = array_combine($test, $test);

$a = new Mva\Mongo\ParamBuilder();

//where test
$a->addWhere('domain', 'branch');
Assert::same(['domain' => 'branch'], $a->where);

$a->addWhere('domain = ?', 'branch');
Assert::same(['domain' => 'branch'], $a->where['$and'][1]);

$a->addWhere('domain = bus');
Assert::same(['domain' => 'bus'], $a->where['$and'][2]);

$a->addWhere('domain <> ?', 'branch');
Assert::same(['domain' => ['$ne' => 'branch']], $a->where['$and'][3]);

$a->addWhere('index.tx >= 5');
Assert::same(['index.tx' => ['$gte' => '5']], $a->where['$and'][4]);

$a->addWhere('index.tx.ax < ?', 2);
Assert::same(['index.tx.ax' => ['$lt' => 2]], $a->where['$and'][5]);

$a->addWhere('domain', $test2);
Assert::same(['domain' => ['$in' => $test]], $a->where['$and'][6]);

$a->addWhere('domain IN ?', $test2);
Assert::same(['domain' => ['$in' => $test]], $a->where['$and'][7]);

$a->addWhere('domain NOT IN', $test2);
Assert::same(['domain' => ['$nin' => $test]], $a->where['$and'][8]);

$a->addWhere('domain EXISTS', TRUE);
Assert::same(['domain' => ['$exists' => TRUE]], $a->where['$and'][9]);

$a->addWhere('domain', ['$exists' => TRUE]);
Assert::same(['domain' => ['$exists' => TRUE]], $a->where['$and'][10]);

//having test - same as where
$a->addHaving('domain <> ?', 'branch');
Assert::same(['domain' => ['$ne' => 'branch']], $a->having);

$a->addHaving('domain IN ?', $test);
Assert::same(['domain' => ['$in' => $test]], $a->having['$and'][1]);

//select test
$a->addSelect('domain');
Assert::same(['domain' => TRUE], $a->select);

$a->addSelect('index');
Assert::same(['domain' => TRUE, 'index' => TRUE], $a->select);

$a->addSelect(['domain', 'coord']);
Assert::same(['domain' => TRUE, 'index' => TRUE, 'coord' => TRUE], $a->select);

//unselect test
$a->addUnselect('_id');
Assert::same(['domain' => TRUE, 'index' => TRUE, 'coord' => TRUE, '_id' => FALSE], $a->select);

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

