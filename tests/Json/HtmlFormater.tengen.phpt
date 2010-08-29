<?php

use Phongo\Json\Serialiser;
use Phongo\Json\HtmlFormater;

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
    'def' => array('a' => 'testint', 'b' => 'JSON', 'c' => 'formating'),
    'ghi' => array(1, 2, array(1, 2, 3), 4, 5),
    'jkl' => new TestObject2,
    'mno' => array(1, 2, new TestObject1, array(new TestObject1, new TestObject1), 3, 4),
    'id' => new Phongo\ObjectId('1234567890ABCDEF12345678'),
    'ref' => new Phongo\Reference('1234567890ABCDEF12345678', 'Coll-1', 'Db-1'),
    'date' => new Phongo\DateTime('2010-08-19 14:09:00'),
    'regex' => new Phongo\Regex('/^[0-9a-f]+$/i'),
    'binary' => new Phongo\BinData("\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"),
    //'code' => new Phongo\Code(' ... '),
    'minkey' => new MongoMinKey,
    'maxkey' => new MongoMaxKey,
);

$serialiser = new Serialiser(new HtmlFormater(TRUE));

echo $serialiser->encode($struct);

__halt_compiler() ?>

------EXPECT------
{
    "abc": null,
    "def": {
        "a": "testint",
        "b": "JSON",
        "c": "formating"
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
        ], 3, 4],
    "id": ObjectId("1234567890ABCDEF12345678"),
    "ref": Dbref("Coll-1", "1234567890ABCDEF12345678"),
    "date": Date("2010-08-19 14:09:00"),
    "regex": /^[0-9a-f]+$/i,
    "binary": { "$binary": "AAECAwQFBgcICQoLDA0ODw==", "$type": "2" },
    "minkey": { "$minKey": 1 },
    "maxkey": { "$maxKey": 1 }
}