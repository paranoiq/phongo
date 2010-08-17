<?php

namespace Phongo;

use Nette\Json;
use Nette\JsonException;


/**
 * Traverses and encodes structures to JSON
 */
class JsonSerialiser {
    
    /** @var Phongo\IJsonFormater */
    private $formater;
    
    /** @var string */
    private $buffer = '';
    
    /** @var integer */
    private $chunkSize = 32768;
    /** @var callback */
    private $outputCallback;
    
    /** @param integer */
    public $maxDepth = 50;
    /** @param integer */
    private $depth = 0;
    
    
    /**
     * @param Phongo\IJsonFormater
     * @param callback
     * @param integer
     */
    public function __construct(IJsonFormater $formater = NULL, $outputCallback = NULL, $chunkSize = NULL) {
        $this->formater = $formater;
        if (isset($outputCallback)) $this->setOutputCallback($outputCallback, $chunkSize);
    }
    
    /**
     * @param callback
     * @param integer
     */
    public function setOutputCallback($outputCallback, $chunkSize = NULL) {
        if (!is_callable($outputCallback))
            throw new \InvalidArgumentException('Output callback must be callable.');
        $this->outputCallback = $outputCallback;
        if (isset($chunkSize)) $this->chunkSize = (int) $chunkSize;
    }
    
    private function flush() {
        call_user_func($this->outputCallback, substr($this->buffer, 0, $chunkSize));
        $this->buffer = substr($this->buffer, $chunkSize);
    }
    
    
    /**
     * @param mixed
     * @return string     
     */
    public function encode($object) {
        $this->_encode();
        
        // finish
        if (isset($this->outputCallback)) $this->flush();
        return $this->buffer;
    }
    
    
    public function _encode($object) {
        // indexed array
        if (is_array($val) && (!$val || array_keys($val) === range(0, count($val) - 1))) {
            $tmp = array();
            foreach ($val as $k => $v) {
                $tmp[] = $this->_encode($v, $depth + 1);
            }
            if (!$tmp) return $this->wrapArray('');
            return $this->wrapArray("\n" . str_repeat($this->indent, $depth) 
                . implode(",\n" . str_repeat($this->indent, $depth), $tmp) 
                . "\n" . str_repeat($this->indent, $depth - 1));
        }
        
        // associative array
        if (is_array($val) || is_object($val)) {
            $tmp = array();
            foreach ($val as $k => $v) {
                $tmp[] = $this->wrapKey($this->_json((string)$k, $depth + 1)) . ': ' . $this->_json($v, $depth + 1);
            }
            if (!$tmp) return $this->wrapObject('');
            return $this->wrapObject(
                (($depth - 1) ? "\n" . str_repeat($this->indent, $depth) : '')
                . implode(",\n" . str_repeat($this->indent, $depth), $tmp) 
                /*. "\n" . str_repeat($this->indent, $depth - 1)*/);
        }
        
        if (is_string($val)) {
            $val = str_replace(array("\\", "\x00"), array("\\\\", "\\u0000"), $val); // due to bug #40915
            return $this->wrapString(addcslashes($val, "\x8\x9\xA\xC\xD/\""));
        }
        
        if (is_int($val) || is_float($val)) {
            return $this->wrapNumber(rtrim(rtrim(number_format($val, 13, '.', ''), '0'), '.'));
        }
        
        if (is_bool($val)) {
            return $val ? $this->wrapBool('true') : $this->wrapBool('false');
        }
        
        return $this->wrapBool('null');
    }
    
    
}
