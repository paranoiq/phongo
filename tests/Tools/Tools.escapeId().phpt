<?php

use Phongo\Tools;

require __DIR__ . '/../initialize.php';

Assert::same(Tools::escapeId('a'), 'a');
Assert::same(Tools::escapeId('whatever'), 'whatever');
Assert::same(Tools::escapeId('what_ever'), 'what_5fever');
Assert::same(Tools::escapeId('_?![]'), '_5f_3f_21_5b_5d');

Assert::same(Tools::unescapeId('a'), 'a');
Assert::same(Tools::unescapeId('whatever'), 'whatever');
Assert::same(Tools::unescapeId('what_5fever'), 'what_ever');
Assert::same(Tools::unescapeId('_5f_3f_21_5b_5d'), '_?![]');


try {
	T::note("Invalid id #1");
	Tools::escapeId(chr(0));
} catch (Exception $e) {
	T::dump( $e );
}

try {
	T::note("Invalid id #2");
	Tools::escapeId(chr(127));
} catch (Exception $e) {
	T::dump( $e );
}

try {
	T::note("Invalid id #3");
	Tools::escapeId(chr(128));
} catch (Exception $e) {
	T::dump( $e );
}

try {
	T::note("Invalid code #1");
	Tools::unescapeId('xx_');
} catch (Exception $e) {
	T::dump( $e );
}

try {
	T::note("Invalid code #2");
	Tools::unescapeId('_xx');
} catch (Exception $e) {
	T::dump( $e );
}

try {
	T::note("Invalid code #3");
	Tools::unescapeId('!');
} catch (Exception $e) {
	T::dump( $e );
}


__halt_compiler() ?>

------EXPECT------
Invalid id #1

Exception InvalidArgumentException: Id contains an invalid character!

Invalid id #2

Exception InvalidArgumentException: Id contains an invalid character!

Invalid id #3

Exception InvalidArgumentException: Id contains an invalid character!

Invalid code #1

Exception InvalidArgumentException: Inproperly escaped id given!

Invalid code #2

Exception InvalidArgumentException: Inproperly escaped id given!

Invalid code #3

Exception InvalidArgumentException: Inproperly escaped id given!
