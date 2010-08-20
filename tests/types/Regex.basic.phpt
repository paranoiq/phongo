<?php

use Phongo\Regex;

require __DIR__ . '/../initialize.php';

$regex = new Regex('/^[0-9a-f]+$/i');
echo $regex;
echo "\n";

$regex = new Regex(new MongoRegex('/^[0-9a-f]+$/i'));
echo $regex;
echo "\n";

__halt_compiler() ?>

------EXPECT------
/^[0-9a-f]+$/i
/^[0-9a-f]+$/i