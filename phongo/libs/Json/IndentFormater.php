<?php

namespace Phongo\Json;

use Nette\Json;


/**
 * JSON formater with indentation and Extended JSON 10gen types 
 */
class IndentFormater extends Formater {
    
    const NONE = 0;
    const OBJ = 1;
    const ARR = 2;
    
    
    /** @var bool use Extended JSON 10gen types */
    public $tenGen = FALSE;
    
    /** @var int indentation level */
    private $level = 0;
    /** @var array */
    private $context = array(self::NONE);
    /** @var int */
    private $prev = 0;
    
    
    public function __construct($tenGen = FALSE) {
        $this->tenGen = (bool) $tenGen;
    }
    
    public function indent() {
        return str_repeat('    ', $this->level);
    }
    
    public function formatObjectId($id) {
        return '{"$oid": "' . $id . '"}';
    }
    
    public function formatReference($id, $collection, $database = NULL) {
        return '{"$ref": "' . $collection . '", "$id": "' . $id . (isset($db) ? '", "$db": "' . $database : '') . '"}';
    }
    
    public function formatDate($value) {
        return '{"$date": "' . $value . '"}';
    }
    
    public function formatRegex($regex, $params) {
        return '{"$regex": "' . $regex . '", "$options": "' . $params . '"}';
    }
    
    public function formatBinary($data, $type) {
        return '{"$binary": "' . base64_encode($data) . '", "$type": "' . $type . '"}';
    }
    
    public function formatCode($code) {
        /// pravděpodobně binary type MongoBinData::FUNC
        throw new Exception('NYI');
    }
    
    public function beginArray() {
        $ret = '';
        if ($this->prev == self::OBJ) $ret = "\n" . $this->indent();
        $this->context[] = self::ARR;
        $this->level++;
        return $ret . '[';
    }
    
    public function endArray() {
        array_pop($this->context);
        $this->level--;
        $ret = '';
        if ($this->prev == self::OBJ) $ret = "\n" . $this->indent();
        $this->prev = self::ARR;
        return $ret . ']';
    }
    
    public function beginObject() {
        $ret = '';
        if ($this->context[$this->level] == self::ARR) $ret = "\n" . $this->indent(); 
        $this->context[] = self::OBJ;
        $this->level++;
        return $ret . '{ ';
    }
    
    public function endObject() {
        array_pop($this->context);
        $this->level--;
        $this->prev = self::OBJ;
        return "\n" . $this->indent() . '}';
    }
    
    public function beginPair() {
        $this->prev = self::NONE;
        return "\n" . $this->indent();
    }
    
    public function formatKey($key) {
        return Json::encode((string) $key) . ': ';
    }
    
    public function nextItem() {
        return ', ';
    }
    
}
