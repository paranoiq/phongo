<?php

use Phongo\Tools;

require __DIR__ . '/../initialize.php';

Assert::false(Tools::validateKeyName(''));
Assert::true(Tools::validateKeyName('a'));
Assert::true(Tools::validateKeyName('whatever'));
Assert::false(Tools::validateKeyName('namespace.whatever'));
Assert::false(Tools::validateKeyName('$whatever'));
Assert::true(Tools::validateKeyName('what$ever'));
Assert::true(Tools::validateKeyName('žlutý-kůň'));

// chars
Assert::false(Tools::validateKeyName(' '));
Assert::false(Tools::validateKeyName('.'));
Assert::false(Tools::validateKeyName(chr(0)));
Assert::false(Tools::validateKeyName(chr(127)));
Assert::true(Tools::validateKeyName(chr(128)));
