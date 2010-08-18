<?php

namespace Phongo\Json;

use Phongo\Object;
use Nette\Json;


/**
 * Formater for JSON serialiser. Including features used by MongoDB
 */
interface IFormater {
    /**#@+ @return string */
    public function formatBool($value);
    public function formatInt($value);
    public function formatFloat($value);
    public function formatString($value);
    public function formatId($id);
    public function formatReference($id, $collection, $database = NULL);
    public function formatDate($value);
    public function formatRegex($regex, $params);
    public function formatBinary($data, $type);
    public function formatCode($code);
    public function beginArray();
    public function endArray();
    public function beginObject();
    public function endObject();
    public function beginPair();
    public function endPair();
    public function formatKey($key);
    public function beginValue();
    public function endValue();
    public function nextItem();
    /**#@-*/
}


class Formater extends Object implements IFormater {
    
    /**#@+ @return string */
    public function formatBool($value) {
        //if ($value === NULL) return 'null';
        //return $value ? 'true' : 'false';
        return Json::encode($value);
    }
    
    public function formatInt($value) {
        //return (string) $value;
        return Json::encode($value);
    }
    
    public function formatFloat($value) {
        //$num = rtrim(number_format($val, 13, '.', ''), '0');
        //return str_pos($num, '.') ? $num . '0' : $num;
        return Json::encode($value);
    }
    
    public function formatString($value) {
        return Json::encode($value);
    }
    
    public function formatId($id) {
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
        return '[';
    }
    
    public function endArray() {
        return ']';
    }
    
    public function beginObject() {
        return '{';
    }
    
    public function endObject() {
        return '}';
    }
    
    public function beginPair() {
        return '';
    }
    
    public function endPair() {
        return '';
    }
    
    public function formatKey($key) {
        return Json::encode((string) $key) . ':';
    }
    
    public function beginValue() {
        return '';
    }
    
    public function endValue() {
        return '';
    }
    
    public function nextItem() {
        return ',';
    }
    /**#@-*/
}