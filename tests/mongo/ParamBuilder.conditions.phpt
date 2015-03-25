<?php

namespace Test;

use Mva,
    Tester\Assert;

$database = require __DIR__ . "/../bootstrap.php";

$a = new Mva\Mongo\ParamBuilder();

$a->addWhere('$or', ['size > 10', 'score < ?' => 20, 'domain EXISTS' => TRUE]);

Assert::same(['$or' => ['size' => ['$gt' => '10'], 'score' => ['$lt' => 20], 'domain' => ['$exists' => TRUE]]], $a->where);

$b = new Mva\Mongo\ParamBuilder();

$b->addWhere('results ELEM MATCH', ['size' => 10, 'score < ?' => 20, 'width > 10']);

Assert::same(['results' => ['$elemMatch' => ['size' => 10, 'score' => ['$lt' => 20], 'width' => ['$gt' => '10']]]], $b->where);