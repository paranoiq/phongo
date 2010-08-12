<?php

use Phongo\Tools;

require __DIR__ . '/../initialize.php';

Assert::false(Tools::validateCollectionName(''));
Assert::true(Tools::validateCollectionName('a'));
Assert::true(Tools::validateCollectionName('whatever'));
Assert::true(Tools::validateCollectionName('namespace.whatever'));
Assert::true(Tools::validateCollectionName('more.namespace.whatever'));
Assert::false(Tools::validateCollectionName('.whatever'));
Assert::false(Tools::validateCollectionName('whatever.'));

// chars
Assert::false(Tools::validateCollectionName(' '));
Assert::false(Tools::validateCollectionName('"'));
Assert::false(Tools::validateCollectionName('$'));
Assert::false(Tools::validateCollectionName('.'));
Assert::false(Tools::validateCollectionName(chr(0)));
Assert::false(Tools::validateCollectionName(chr(127)));
Assert::false(Tools::validateCollectionName(chr(128)));

// MongoDB system collections
Assert::false(Tools::validateCollectionName('$cmd.sys.abc'));
Assert::false(Tools::validateCollectionName('system.abc'));
Assert::false(Tools::validateCollectionName('local.abc'));
Assert::true(Tools::validateCollectionName('$cmd.sys.abc', TRUE));
Assert::true(Tools::validateCollectionName('system.abc', TRUE));
Assert::true(Tools::validateCollectionName('local.abc', TRUE));
