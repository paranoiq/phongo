<?php

namespace Phongo\Json;

use Phongo\Object;
use Nette\Json;
use Nette\JsonException;

use Phongo\ObjectId;
use Phongo\Reference;
use Phongo\Regex;
use Phongo\BinData;


/**
 * Traverses and encodes structures to JSON
 * 
 * Set output callback to get results in chunks - for big amount of data
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
        call_user_func($this->outputCallback, substr($this->buffer, 0, $this->chunkSize));
        $this->buffer = substr($this->buffer, $this->chunkSize);
    }
    
    
    /**
     * @param mixed
     * @return string     
     */
    public function encode($value) {
        $this->_encode($value);
        
        // empty root element is always object
        if ($this->buffer == '[]') $this->buffer = '{}';
        // finish
        if (isset($this->outputCallback)) $this->flush();
        return $this->buffer;
    }
    
    
    public function _encode($value) {
        $this->depth++;
        if ($this->depth > $this->maxDepth) 
            throw new JsonException('Maximal recursion depth reached.');
        
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
            $next = FALSE;
            foreach ($value as $key => $val) {
                if (!$next) {
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
            $next = FALSE;
            foreach ($value as $key => $val) {
                if (!$next) {
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
            /***/ if ($value instanceof ObjectId) {
                $this->buffer .= $this->formater->formatObjectId($value->id);
            } elseif ($value instanceof \MongoId) {
                $this->buffer .= $this->formater->formatObjectId((string) $value);
                
            } elseif ($value instanceof Reference) {
                $this->buffer .= $this->formater->formatReference($value->id, $value->collection, $value->database);
                    
            } elseif ($value instanceof \DateTime) {
                $this->buffer .= $this->formater->formatDate($value->format('Y-m-d H:i:s'));
            } elseif ($value instanceof \MongoDate) {
                $value = new DateTime($value);
                $this->buffer .= $this->formater->formatDate($value->format('Y-m-d H:i:s'));
                
            } elseif ($value instanceof Regex) {
                $this->buffer .= $this->formater->formatRegex($value->regex, $value->flags);
            } elseif ($value instanceof \MongoRegex) {
                $this->buffer .= $this->formater->formatRegex($value->regex, $value->flags);
                
            } elseif ($value instanceof BinData) {
                $this->buffer .= $this->formater->formatBinData($value->data, \MongoBinData::BYTE_ARRAY);
            } elseif ($value instanceof \MongoBinData) {
                $this->buffer .= $this->formater->formatBinData($value->bin, $value->type);
                
            } elseif ($value instanceof \MongoMinKey) {
                $this->buffer .= $this->formater->formatMinKey();
            } elseif ($value instanceof \MongoMaxKey) {
                $this->buffer .= $this->formater->formatMaxKey();
            
            } else {
                $this->buffer .= $this->formater->beginObject();
                $next = FALSE;
                foreach (new ObjectIterator($value) as $key => $val) {
                    if (!$next) {
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
            }
            
        } else {
            throw new JsonException('Resource cannot be serialised to JSON.');
        }
        
        if (isset($this->outputCallback) && strlen($this->buffer) >= $this->chunkSize) $this->flush();
        $this->depth--;
    }
    
}
