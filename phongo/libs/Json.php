<?php

namespace Phongo;


if (!class_exists('Nette\Json', FALSE)) {
    require_once dirname(__FILE__) . '/Nette/Json.php';
}


class Json {
    
    /** Static class - cannot be instantiated. */
    final public function __construct() {
        throw new \LogicException("Cannot instantiate static class " . get_class($this));
    }
    
    
    public static function encode($object) {
        ///
        
        return \Nette\Json::encode($object);
    }
    
    
    public static function decode($string) {
        ///
        
        return \Nette\Json::decode($string, TRUE/* assoc */);
    }
    
    
    public static function tenGenToJson() {
        ///
    }
    
    
    public static function jsonToTenGen() {
        ///
    }
    
    
    
}
