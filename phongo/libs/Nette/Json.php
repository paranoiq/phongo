<?php

/**
 * Nette Framework
 *
 * @copyright  Copyright (c) 2004, 2010 David Grudl
 * @license    http://nette.org/license  Nette license
 * @link       http://nette.org
 * @category   Nette
 * @package    Nette
 */

namespace Nette;

use Nette;



/**
 * JSON encoder and decoder.
 *
 * @copyright  Copyright (c) 2004, 2010 David Grudl
 * @package    Nette
 */
final class Json
{
	/** @var array */
	private static $messages = array(
		JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
		JSON_ERROR_STATE_MISMATCH => 'Syntax error, malformed JSON',
		JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
		JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
	);



	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new \LogicException("Cannot instantiate static class " . get_class($this));
	}



	/**
	 * Returns the JSON representation of a value.
	 * @param  mixed
	 * @return string
	 */
	public static function encode($value, $options = 0)
	{
		$old_er = error_reporting(0);
		trigger_error(''); // "reset" error_get_last
		
		if (function_exists('ini_set')) {
			$old = ini_set('display_errors', 0); // needed to receive 'Invalid UTF-8 sequence' error
			$json = json_encode($value, $options);
			ini_set('display_errors', $old);
		} else {
			$json = json_encode($value);
		}
		
		error_reporting($old_er);
		$error = error_get_last(); // needed to receive 'recursion detected' error
		if ($error && $error['message'] !== '') {
			throw new JsonException($error['message']);
			
		}
        
		return $json;
	}



	/**
	 * Decodes a JSON string.
	 * @param  string
	 * @return mixed
	 */
	public static function decode($json, $assoc = false, $depth = 512, $options = 0)
	{
		$json = (string) $json;
		$value = json_decode($json, $assoc, $depth, $options);
		if ($value === NULL && $json !== '' && strcasecmp($json, 'null')) { // '' do not clean json_last_error
			$error = PHP_VERSION_ID >= 50300 ? json_last_error() : 0;
			throw new JsonException(isset(self::$messages[$error]) ? self::$messages[$error] : 'Unknown error', $error);
		}
		return $value;
	}

}



/**
 * The exception that indicates error of JSON encoding/decoding.
 * @package    Nette
 */
class JsonException extends \Exception
{
}
