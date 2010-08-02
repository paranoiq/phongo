<?php

namespace Phongo;


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
     * @param Phongo\Connection
     * @return array
     */
    public function getFrom(IConnection $connection) {
        $collection->get($this);
    }
    
}
