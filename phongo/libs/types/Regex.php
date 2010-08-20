<?php

namespace Phongo;

use MongoRegex;


/**
 * MongoDB object id wrapper
 * 
 * @property-read regex
 * @property-read flags
 */
class Regex extends Object {
    
    protected $regex;
    protected $flags;
    protected $delim = '/';
    
    
    /** @param string
     *  @param string */
    public function __construct($regex) {
        if ($regex instanceof MongoRegex) {
            $this->regex = $regex->regex;
            $this->flags = $regex->flags;
            /// detect delimeter somehow?
        } else {
            $this->delim = substr($regex, 0, 1);
            $end = strlen($regex) - strpos(strrev($regex), $this->delim);
            $this->regex = substr($regex, 1, $end - 2);
            $this->flags = substr($regex, $end);
        }
    }
    
    /** @return MongoRegex */
    public function getMongoRegex() {
        return new MongoRegex($this->delim . $this->regex . $this->delim . $this->flags);
    }
    
    /** @return string */
    public function getRegex() {
        return $this->regex;
    }
    
    /** @return string */
    public function getFlags() {
        return $this->flags;
    }
    
    /** @return string */
    public function __toString() {
        return $this->delim . $this->regex . $this->delim . $this->flags;
    }
    
}
