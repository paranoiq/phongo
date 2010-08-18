<?php

namespace Phongo\Json;

use Phongo\Object;
use Nette\Json;
use Nette\JsonException;


/**
 * Traverses and encodes structures to JSON
 */
class Serialiser extends Object {
    
    /** @var Phongo\Json\IFormater */
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
     * @param Phongo\Json\IFormater
     * @param callback
     * @param integer
     */
    public function __construct(IFormater $formater, $outputCallback = NULL, $chunkSize = NULL) {
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
    public function encode($value) {
        $this->_encode($value);
        
        // finish
        if (isset($this->outputCallback)) $this->flush();
        return $this->buffer;
    }
    
    
    public function _encode($value) {
        $this->depth++;
        if ($this->depth > $this->maxDepth) 
            throw new JsonException('Maxmal recursion depth reached.');
        
         
        if (is_string($value)) {
            $this->buffer .= $this->formater->formatString($value);
            
        } elseif (is_int($value)) {
            $this->buffer .= $this->formater->formatInt($value);
            
        } elseif (is_float($value)) {
            $this->buffer .= $this->formater->formatFloat($value);
            
        } elseif (is_bool($value)) {
            $this->buffer .= $this->formater->formatBool($value);
            
        } elseif (is_null($value)) {
            $this->buffer .= $this->formater->formatBool($value);
            
        // indexed array
        } elseif (is_array($value) && (!$value || array_keys($value) === range(0, count($value) - 1))) {
            $this->buffer .= $this->formater->beginArray();
            foreach ($value as $key => $val) {
                if (!isset($next)) {
                    $next = TRUE;
                } else {
                    $this->buffer .= $this->formater->nextItem();
                }
                $this->buffer .= $this->formater->beginValue();
                $this->_encode($val);
                $this->buffer .= $this->formater->endValue();
            }
            $this->buffer .= $this->formater->endArray();
            
        // associative array
        } elseif (is_array($value)) {
            $this->buffer .= $this->formater->beginObject();
            foreach ($value as $key => $val) {
                if (!isset($next)) {
                    $next = TRUE;
                } else {
                    $this->buffer .= $this->formater->nextItem();
                }
                $this->buffer .= $this->formater->beginPair();
                $this->buffer .= $this->formater->formatKey($key);
                $this->buffer .= $this->formater->beginValue();
                $this->_encode($val);
                $this->buffer .= $this->formater->endValue();
                $this->buffer .= $this->formater->endPair();
            }
            $this->buffer .= $this->formater->endObject();
            
        } elseif (is_object($value)) {
            $this->buffer .= $this->formater->beginObject();
            foreach (new ObjectIterator($value) as $key => $val) {
                if (!isset($next)) {
                    $next = TRUE;
                } else {
                    $this->buffer .= $this->formater->nextItem();
                }
                $this->buffer .= $this->formater->beginPair();
                $this->buffer .= $this->formater->formatKey($key);
                $this->buffer .= $this->formater->beginValue();
                $this->_encode($val);
                $this->buffer .= $this->formater->endValue();
                $this->buffer .= $this->formater->endPair();
            }
            $this->buffer .= $this->formater->endObject();
            
        } else {
            throw new JsonException('Resource cannot be serialised to JSON.');
        }
        
        $this->depth--;
    }
    
}
