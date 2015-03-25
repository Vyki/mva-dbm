<?php

namespace Test;

use Mva,   
    Tester\Assert,
    Tester\TestCase;

$database = require __DIR__ . "/../bootstrap.php";

class AggregateTest extends TestCase
{
    private $database;

    function __construct($database)
    {
        $this->database = $database;
    }
    
    /** @return Mva\Mongo\Selection */
    function getCollection()
    {
		exec("mongoimport --db mva_test --drop --collection test_agr < " . __DIR__ . "/test.json");
        return new Mva\Mongo\Collection('test_agr', $this->database);
    }
    
    function testCount()
    {
        $collection = $this->getCollection();
        
        $count = $collection->count();

        Assert::equal(6, $count);

        $collection->where(['pr_id' => 2]);

        $count2 = $collection->count();

        Assert::equal(3, $count2);
    }
    
    function testMaxMinSum()
    {
        $collection = $this->getCollection();
        
        $max = $collection->max('size');
        $min = $collection->min('size');
        
        Assert::equal(101, $max);
        Assert::equal(10, $min);
        
        $collection->where('domain', 'beta');
        
        $sum = $collection->sum('size');
        Assert::equal(199, $sum);
        
        $sum_not_number = $collection->sum('domain');
        Assert::equal(0, $sum_not_number);
        
        $sum_undefined = $collection->sum('fake');
        Assert::equal(0, $sum_undefined);
    }
    
    function testFullAggregation()
    {
        $collection = $this->getCollection();
        $collection->select('SUM(size) AS size_total');
        $collection->group('domain');
        $collection->where('size > ?', 10);
        
        $beta = $collection->fetch();  
		
		Assert::true($beta instanceof Mva\Mongo\AggregatedDocument);
		
        Assert::equal('beta', $beta['domain']);
        Assert::equal(199, $beta['size_total']);
        
        $alpha = $collection->fetch(); 
		
        Assert::equal('alpha', $alpha['domain']);
        Assert::equal(82, $alpha['size_total']);
        
        Assert::equal(2, $collection->count());
        
        $collection->having('size_total > ?', 82);
        
        $having_test = $collection->fetch();
        
        Assert::equal(1, $collection->count());
        Assert::equal('beta', $having_test['domain']);
        Assert::equal(199, $having_test['size_total']);
    }
}

$test = new AggregateTest($database);
$test->run();




