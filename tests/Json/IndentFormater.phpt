<?php

use Phongo\Json\Serialiser;
use Phongo\Json\IndentFormater;

require __DIR__ . '/../initialize.php';


class TestObject1 {
    public $abc = 123;
    public $def = 456;
    public $ghi = 789;
}

class TestObject2 {
    public $abc = 123;
    public $arr = array(123, 456, 789, array(123, 456, 789));
}

$struct = array(
    'abc' => NULL,
    'def' => array('a' => 1, 'b' => 2, 'c' => 3),
    'ghi' => array(1, 2, array(1, 2, 3), 4, 5),
    'jkl' => new TestObject2,
    'mno' => array(1, 2, new TestObject1, new TestObject1, array(new TestObject1, new TestObject1), 3, 4)
);

$serialiser = new Serialiser(new IndentFormater);

echo $serialiser->encode($struct);

__halt_compiler() ?>

------EXPECT------
{ 
    "abc": null, 
    "def": { 
        "a": 1, 
        "b": 2, 
        "c": 3
    }, 
    "ghi": [1, 2, [1, 2, 3], 4, 5], 
    "jkl": { 
        "abc": 123, 
        "arr": [123, 456, 789, [123, 456, 789]]
    }, 
    "mno": [1, 2, 
        { 
            "abc": 123, 
            "def": 456, 
            "ghi": 789
        }, 
        { 
            "abc": 123, 
            "def": 456, 
            "ghi": 789
        }, 
        [
            { 
                "abc": 123, 
                "def": 456, 
                "ghi": 789
            }, 
            { 
                "abc": 123, 
                "def": 456, 
                "ghi": 789
            }
        ], 3, 4]
}