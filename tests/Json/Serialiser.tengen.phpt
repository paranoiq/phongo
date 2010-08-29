<?php

use Phongo\Json\Serialiser;
use Phongo\Json\BasicFormater;

require __DIR__ . '/../initialize.php';

// test 10gen objects

$struct = array(
    'id' => new Phongo\ObjectId('1234567890ABCDEF12345678'),
    'ref' => new Phongo\Reference('1234567890ABCDEF12345678', 'Coll-1', 'Db-1'),
    'date' => new Phongo\DateTime('2010-08-19 14:09:00'),
    'regex' => new Phongo\Regex('/^[0-9a-f]+$/i'),
    'binary' => new Phongo\BinData("\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"),
    //'code' => new Phongo\Code(' ... '),
    'minkey' => new MongoMinKey,
    'maxkey' => new MongoMaxKey,
);

$serialiser = new Serialiser(new BasicFormater);

echo $serialiser->encode($struct);

__halt_compiler() ?>

------EXPECT------
{"id":{"$oid":"1234567890ABCDEF12345678"},"ref":{"$ref":"Coll-1","$id":"1234567890ABCDEF12345678","$db":"Db-1"},"date":{"$date":"2010-08-19 14:09:00"},"regex":{"$regex":"^[0-9a-f]+$","$options":"i"},"binary":{"$binary":"AAECAwQFBgcICQoLDA0ODw==","$type":"2"},"minkey":{"$minKey":1},"maxkey":{"$maxKey":1}}