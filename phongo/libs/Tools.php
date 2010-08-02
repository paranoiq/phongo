<?php

namespace Phongo;


class Tools {
    
    /** Static class - cannot be instantiated. */
    final public function __construct() {
        throw new \LogicException("Cannot instantiate static class " . get_class($this));
    }
    
    
    /**
     * Recursive array mapping functin. No params for callback
     * 
     * @param callback
     * @param array<array>
     * @return array<array>
     */
    public static function map($array, $callback) {
        $return = array();
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                $return[$key] = self::map($item, $callback);
            } else {
                $return[$key] = $callback($item);
            }
        }
        return $return;
    }
    
    
    /**
     * @param string
     * @return string
     */
    public static function escapeId($id) {
        if (preg_match('/[\x80-\xFF]/', $id))
            throw new \InvalidArgumentException('Id contains an invalid character!');
        return preg_replace_callback('/[^0-9A-Za-z]/', function ($char) { return '_' . bin2hex($char[0]); }, $id);
    }
    
    
    /**
     * @param string
     * @return string
     */
    public static function unescapeId($id) {
        if (!preg_match('/[0-9A-Za-z_]/', $id) || preg_match('/_([^0-7].|.[^0-9A-Fa-f])/', $id))
            throw new \InvalidArgumentException('Inproperly escaped id given!');
        return preg_replace_callback('/_[0-9A-Fa-f]{2}/', function ($code) { return pack('H*', substr($code[0], 1, 2)); }, $id);
    }
    
}
