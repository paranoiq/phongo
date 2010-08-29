<?php

use Phongo\Json\Serialiser;
use Phongo\Json\BasicFormater;

require __DIR__ . '/../initialize.php';

// streaming callback test

class TestObject {
    public $public = 'public';
}

$struct = array(
    'boolean' => array(TRUE, FALSE, NULL),
    'number' => array(1, -2, 3.333, 4e17),
    'string' => array('abc', 'a\b"c', "žlutý kůň", 'a'.chr(0).'z'),
    'array' => array(array()),
    'object' => new TestObject()
);

$serialiser = new Serialiser(new BasicFormater);

function ocb($data) {
    echo $data . "\n--------\n";
}

$serialiser->setOutputCallback('ocb', 40);

echo $serialiser->encode($struct);

__halt_compiler() ?>

------EXPECT------
{"boolean":[true,false,null],"number":[1
--------
,-2,3.333,4.0e+17],"string":["abc","a\\b
--------
\"c","\u017elut\u00fd k\u016f\u0148","a\
--------
u0000z"],"array":[[]],"object":{"public"
--------
:"public"}}
--------
