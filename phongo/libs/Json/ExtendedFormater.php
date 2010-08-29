<?php

namespace Phongo\Json;

use Nette\Json;


/**
 * "JSON" formater with indentation and Extended JSON 10gen types
 * Some settings are not compatible with pure JSON format
 */
class ExtendedFormater extends BasicFormater {
    
    const NO_CONTEXT = 0;
    const OBJECT_CONTEXT = 1;
    const ARRAY_CONTEXT = 2;
    
    
    /** @var bool use Extended JSON 10gen types */
    public $tenGen = FALSE;
    /** @var bool */
    public $quoteKeys = TRUE;
    /** @var bool */
    public $jsonStrings = TRUE;
    
    /** @var int indentation level */
    protected $level = 0;
    /** @var array */
    protected $context = array(self::NO_CONTEXT);
    /** @var int */
    protected $prev = 0;
    
    
    public function __construct($options = array()) {
        $this->tenGen = !empty($options['tengenTypes']);
        $this->quoteKeys = !empty($options['quoteKeys']);
        $this->jsonStrings = !empty($options['jsonStrings']);
    }
    
    protected function indent() {
        return str_repeat('    ', $this->level);
    }
    
    
    public function formatString($value) {
        if ($this->jsonStrings) {
            return parent::formatString($value);
        } else {
            return '"' . str_replace(array('"', '\\', chr(0)), array('\\"', '\\\\', '\\0'), $value) . '"';
        }
    }
    
    public function formatObjectId($id) {
        if ($this->tenGen) return 'ObjectId("' . $id . '")';
        if ($this->quoteKeys) return '{ "$oid": "' . $id . '" }';
        return '{ $oid: "' . $id . '" }';
    }
    
    public function formatReference($id, $collection, $database = NULL) {
        if ($this->tenGen) return 'Dbref("' . $collection . '", "' . $id . (isset($database) ? '", "' . $database : '') . '")';
        if ($this->quoteKeys) return '{ "$ref": "' . $collection . '", "$id": "' . $id . (isset($database) ? '", "$db": "' . $database : '') . '" }';
        return '{ $ref: "' . $collection . '", $id: "' . $id . (isset($database) ? '", $db: "' . $database : '') . '" }';
    }
    
    public function formatDate($value) {
        if ($this->tenGen) return 'Date("' . $value . '")';
        if ($this->quoteKeys) return '{ "$date": "' . $value . '" }';
        return '{ $date: "' . $value . '" }';
    }
    
    public function formatRegex($regex, $params) {
        if ($this->tenGen) return '/' . $regex . '/' . $params;
        if ($this->quoteKeys) return '{ "$regex": "' . $regex . '", "$options": "' . $params . '" }';
        return '{ $regex: "' . $regex . '", $options: "' . $params . '" }';
    }
    
    public function formatBinData($data, $type) {
        if ($this->quoteKeys) return '{ "$binary": "' . base64_encode($data) . '", "$type": "' . $type . '" }';
        return '{ $binary: "' . base64_encode($data) . '", $type: "' . $type . '" }';
    }
    
    public function formatMinKey() {
        if ($this->quoteKeys) return '{ "$minKey": 1 }';
        return '{ $minKey: 1 }';
    }
    
    public function formatMaxKey() {
        if ($this->quoteKeys) return '{ "$maxKey": 1 }';
        return '{ $maxKey: 1 }';
    }
    
    public function formatCode($code) {
        /// pravděpodobně binary type MongoBinData::FUNC
        throw new Exception('NYI');
    }
    
    public function beginArray() {
        $ret = '';
        if ($this->prev == self::OBJECT_CONTEXT) $ret = "\n" . $this->indent();
        $this->context[] = self::ARRAY_CONTEXT;
        $this->level++;
        return $ret . '[';
    }
    
    public function endArray() {
        array_pop($this->context);
        $this->level--;
        $ret = '';
        if ($this->prev == self::OBJECT_CONTEXT) $ret = "\n" . $this->indent();
        $this->prev = self::ARRAY_CONTEXT;
        return $ret . ']';
    }
    
    public function beginObject() {
        $ret = '';
        if ($this->context[$this->level] == self::ARRAY_CONTEXT) $ret = "\n" . $this->indent(); 
        $this->context[] = self::OBJECT_CONTEXT;
        $this->level++;
        return $ret . '{';
    }
    
    public function endObject() {
        array_pop($this->context);
        $this->level--;
        $this->prev = self::OBJECT_CONTEXT;
        return "\n" . $this->indent() . '}';
    }
    
    public function beginPair() {
        $this->prev = self::NO_CONTEXT;
        return "\n" . $this->indent();
    }
    
    public function formatKey($key) {
        if ($this->jsonStrings) {
            $str = parent::formatString((string) $key);
        } else {
            $str = str_replace(array('"', '\\', chr(0)), array('\\"', '\\\\', '\\0'), $key);
        }
        if ($this->quoteKeys && $this->jsonStrings) {
            return $str . ': ';
        } elseif ($this->quoteKeys) {
            return '"' . $str . '": ';
        } else {
            return trim($str, '"') . ': ';
        }
    }
    
    public function nextItem() {
        return ($this->context[$this->level] == self::ARRAY_CONTEXT) ? ', ' : ',';
    }
    
}
