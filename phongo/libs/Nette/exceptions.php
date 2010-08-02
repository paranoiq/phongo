<?php


/**
 * Compatibility with Nette Framework
 */
if (!class_exists('NotImplementedException', FALSE)) {
    /** @package exceptions */
    class NotImplementedException extends LogicException {}
}

if (!class_exists('NotSupportedException', FALSE)) {
    /** @package exceptions */
    class NotSupportedException extends LogicException {}
}

if (!class_exists('MemberAccessException', FALSE)) {
    /** @package exceptions */
    class MemberAccessException extends LogicException {}
}

if (!class_exists('InvalidStateException', FALSE)) {
    /** @package exceptions */
    class InvalidStateException extends RuntimeException {}
}

if (!class_exists('IOException', FALSE)) {
    /** @package exceptions */
    class IOException extends RuntimeException {}
}

if (!class_exists('FileNotFoundException', FALSE)) {
    /** @package exceptions */
    class FileNotFoundException extends IOException {}
}

if (!class_exists('PcreException', FALSE)) {
    /** @package exceptions */
    class PcreException extends Exception {
        
        public function __construct()
        {
            static $messages = array(
                PREG_INTERNAL_ERROR => 'Internal error.',
                PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted.',
                PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted.',
                PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data.',
                5 => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point.', // PREG_BAD_UTF8_OFFSET_ERROR
            );
            $code = preg_last_error();
            parent::__construct(isset($messages[$code]) ? $messages[$code] : 'Unknown error.', $code);
        }
    }
}

