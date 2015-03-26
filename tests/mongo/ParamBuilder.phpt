<?php

namespace Test;

use Mva,
	Tester\Assert;

$database = require __DIR__ . "/../bootstrap.php";

$test = array('bus', 2, 'branch');

$test2 = array_combine($test, $test);

$a = new Mva\Mongo\ParamBuilder();

//where test
$a->addWhere('domain', 'branch');
Assert::same(array('domain' => 'branch'), $a->where);

$a->addWhere('domain = ?', 'branch');
Assert::same(array('domain' => 'branch'), $a->where['$and'][1]);

$a->addWhere('domain = bus');
Assert::same(array('domain' => 'bus'), $a->where['$and'][2]);

$a->addWhere('domain <> ?', 'branch');
Assert::same(array('domain' => array('$ne' => 'branch')), $a->where['$and'][3]);

$a->addWhere('index.tx >= 5');
Assert::same(array('index.tx' => array('$gte' => '5')), $a->where['$and'][4]);

$a->addWhere('index.tx.ax < ?', 2);
Assert::same(array('index.tx.ax' => array('$lt' => 2)), $a->where['$and'][5]);

$a->addWhere('domain', $test2);
Assert::same(array('domain' => array('$in' => $test)), $a->where['$and'][6]);

$a->addWhere('domain IN ?', $test2);
Assert::same(array('domain' => array('$in' => $test)), $a->where['$and'][7]);

$a->addWhere('domain NOT IN', $test2);
Assert::same(array('domain' => array('$nin' => $test)), $a->where['$and'][8]);

$a->addWhere('domain EXISTS', TRUE);
Assert::same(array('domain' => array('$exists' => TRUE)), $a->where['$and'][9]);

$a->addWhere('domain', array('$exists' => TRUE));
Assert::same(array('domain' => array('$exists' => TRUE)), $a->where['$and'][10]);

//having test - same as where
$a->addHaving('domain <> ?', 'branch');
Assert::same(array('domain' => array('$ne' => 'branch')), $a->having);

$a->addHaving('domain IN ?', $test);
Assert::same(array('domain' => array('$in' => $test)), $a->having['$and'][1]);

//select test
$a->addSelect('domain');
Assert::same(array('domain' => TRUE), $a->select);

$a->addSelect('index');
Assert::same(array('domain' => TRUE, 'index' => TRUE), $a->select);

$a->addSelect(array('domain', 'coord'));
Assert::same(array('domain' => TRUE, 'index' => TRUE, 'coord' => TRUE), $a->select);

//unselect test
$a->addUnselect('_id');
Assert::same(array('domain' => TRUE, 'index' => TRUE, 'coord' => TRUE, '_id' => FALSE), $a->select);

$a->addUnselect(array('index', 'pr_id'));
Assert::same(array('domain' => TRUE, 'index' => FALSE, 'coord' => TRUE, '_id' => FALSE, 'pr_id' => FALSE), $a->select);

//order test
$a->addOrder('domain ASC');
Assert::same(array('domain' => 1), $a->order);

$a->addOrder(array('index DESC', 'coord ASC'));
Assert::same(array('domain' => 1, 'index' => -1, 'coord' => 1), $a->order);

//set group test
$a->setGroup(array('coord', 'domain'));
Assert::same(array('coord' => '$coord', 'domain' => '$domain'), $a->group);

$a->setGroup('domain');
Assert::same(array('domain' => '$domain'), $a->group);

//aggregate
$a->addSelect('MAX(domain) AS domain_max');
Assert::same(array('$max' => '$domain'), $a->aggregate['domain_max']);

$a->addSelect('MIN(delta)');
Assert::same(array('$min' => '$delta'), $a->aggregate['_delta_min']);

$a->addAggregate('sum', 'domain', 'dom_sum');
Assert::same(array('$sum' => '$domain'), $a->aggregate['dom_sum']);

$a->addAggregate('sum', '*', 'count');
Assert::same(array('$sum' => 1), $a->aggregate['count']);

Assert::same(array('_id', 'domain_max', '_delta_min', 'dom_sum', 'count'), array_keys($a->aggregate));

