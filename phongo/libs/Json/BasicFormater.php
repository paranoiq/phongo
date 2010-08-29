<?php

namespace Phongo\Json;

use Phongo\Object;
use Nette\Json;


/**
 * Formater for JSON serialiser. Including features used by MongoDB
 */
interface IFormater {
    /**#@+ @return string */
    /** @param bool|null */
    public function formatBool($value);
    /** @param int */
    public function formatInt($value);
    /** @param float */
    public function formatFloat($value);
    /** @param string */
    public function formatString($value);
    /** @param string */
    public function formatObjectId($id);
    /** @param string
     *  @param string
     *  @param string */
    public function formatReference($id, $collection, $database = NULL);
    /** @param string */
    public function formatDate($value);
    /** @param string
     *  @param string */
    public function formatRegex($regex, $params);
    /** @param string
     *  @param int */
    public function formatBinData($data, $type);
    /** @param string */
    public function formatCode($code);
    public function formatMinKey();
    public function formatMaxKey();
    public function beginArray();
    public function endArray();
    public function beginObject();
    public function endObject();
    public function beginPair();
    public function endPair();
    /** @param string */
    public function formatKey($key);
    public function beginValue();
    public function endValue();
    public function nextItem();
    /**#@-*/
}


/**
 * Basic inline JSON fomater
 */
class BasicFormater extends Object implements IFormater {
    
    public function formatBool($value) {
        return json_encode($value);
    }
    
    public function formatInt($value) {
        return json_encode($value);
    }
    
    public function formatFloat($value) {
        return json_encode($value);
    }
    
    public function formatString($value) {
        return Json::encode($value);
    }
    
    public function formatObjectId($id) {
        return '{"$oid":"' . $id . '"}';
    }
    
    public function formatReference($id, $collection, $database = NULL) {
        return '{"$ref":"' . $collection . '","$id":"' . $id . (isset($database) ? '","$db":"' . $database : '') . '"}';
    }
    
    public function formatDate($value) {
        return '{"$date":"' . $value . '"}';
    }
    
    public function formatRegex($regex, $params) {
        return '{"$regex":"' . $regex . '","$options":"' . $params . '"}';
    }
    
    public function formatBinData($data, $type) {
        return '{"$binary":"' . base64_encode($data) . '","$type":"' . $type . '"}';
    }
    
    public function formatCode($code) {
        /// pravděpodobně binary type MongoBinData::FUNC
        throw new Exception('NYI');
    }
    
    public function formatMinKey() {
        return '{"$minKey":1}';
    }
    
    public function formatMaxKey() {
        return '{"$maxKey":1}';
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
    
}
