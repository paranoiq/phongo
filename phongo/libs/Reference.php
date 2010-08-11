<?php

namespace Phongo;


/**
 * MongoDB object reference wrapper
 * 
 * @property-read id
 * @property-read collection
 * @property-read database
 */
class Reference extends Object {
    
    private $id;
    private $collection;
    private $database;
    
    /**
     * @param string
     * @param string
     * @param string
     */
    public function __construct($id, $collection, $database = NULL) {
        $this->id = $id;
        $this->collection = $collection;
        $this->database   = $database;
    }
    
    /**
     * @return array
     */
    public function getMongoDBRef() {
        return MongoDBRef::create($this->collection, $this->id, $this->database);
    }
    
    /**
     * @param Phongo\Connection|Phongo\Database
     * @return array
     */
    public function getObjectFrom($resource) {
        return $resource->get($this);
    }
    
    /**
     * @return string
     */   
    public function getDatabase() {
        return $this->database;
    }
    
    /**
     * @return string
     */   
    public function getCollection() {
        return $this->collection;
    }
    
    /**
     * @return string
     */   
    public function getId() {
        return $this->id;
    }
    
}
