<?php

namespace Test;

use Mva,
	Tester\Assert;

$database = require __DIR__ . "/../bootstrap.php";

$a = new Mva\Mongo\ParamBuilder();

$a->addWhere('$or', array('size > 10', 'score < ?' => 20, 'domain EXISTS' => TRUE));

Assert::same(array(
	'$or' => array(
		array('size' => array('$gt' => '10')),
		array('score' => array('$lt' => 20)),
		array('domain' => array('$exists' => TRUE))
	)), $a->where);

$b = new Mva\Mongo\ParamBuilder();

$b->addWhere('results ELEM MATCH', array('size' => 10, 'score < ?' => 20, 'width > 10'));

Assert::same(array(
	'results' => array(
		'$elemMatch' => array(
			'size' => 10,
			'score' => array('$lt' => 20),
			'width' => array('$gt' => '10')
		)
	)), $b->where);
