<?php

namespace Phongo;

use MongoId;


/**
 * MongoDB object id wrapper
 * 
 * ? MongoId::getHostname
 * ? MongoId::getTimestamp
 * 
 * @property-read id
 */
class ObjectId extends Object {
    
    protected $id;
    
    
    /** @param string */
    public function __construct($id) {
        $this->id = (string) $id;
    }
    
    /** @return MongoId */
    public function getMongoId() {
        return new MongoId($this->id);
    }
    
    /** @return string */
    public function getId() {
        return $this->id;
    }
    
    /** @return string */
    public function __toString() {
        return $this->id;
    }
    
}
