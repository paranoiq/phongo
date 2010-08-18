<?php

namespace Phongo\Json;


/**
 * Iterates through properties of an object even if it implements Traversable interface
 */
class ObjectIterator implements \Iterator {
    
    private $arr;
    
    /** @param object */
    public function __construct($object) {
        $this->arr = (array) $object;
        foreach ($this->arr as $key => $val) {
            if (substr($key, 0, 1)  === "\x00") unset($this->arr[$key]);
        }
    }
    
    public function current() {
        return current($this->arr);
    }
    
    public function next() {
        next($this->arr);
    }
    
    public function key() {
        return key($this->arr);
    }
    
    public function rewind() {
        reset($this->arr);
    }
    
    public function valid() {
        return key($this->arr) !== NULL;
    }
    
}
