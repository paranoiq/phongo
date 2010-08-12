<?php

use Phongo\Tools;

require __DIR__ . '/../initialize.php';


Assert::false(Tools::validateDatabaseName(''));
Assert::true(Tools::validateDatabaseName('a'));
Assert::true(Tools::validateDatabaseName('whatever'));
Assert::false(Tools::validateDatabaseName('namespace.whatever'));

// chars
Assert::false(Tools::validateDatabaseName(' '));
Assert::false(Tools::validateDatabaseName('"'));
Assert::false(Tools::validateDatabaseName('*'));
Assert::false(Tools::validateDatabaseName('$'));
Assert::false(Tools::validateDatabaseName('.'));
Assert::false(Tools::validateDatabaseName('/'));
Assert::false(Tools::validateDatabaseName(':'));
Assert::false(Tools::validateDatabaseName('?'));
Assert::false(Tools::validateDatabaseName('\\'));
Assert::false(Tools::validateDatabaseName('|'));
Assert::false(Tools::validateDatabaseName(chr(0)));
Assert::false(Tools::validateDatabaseName(chr(127)));
Assert::false(Tools::validateDatabaseName(chr(128)));
