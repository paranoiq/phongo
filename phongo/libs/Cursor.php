<?php

namespace Phongo;

use MongoCursor;
use InvalidStateException;


interface ICursor {
    public function __construct(MongoCursor $cursor);
}


/**
 * Database query cursor (selecting, ordering, limits...)
 */
class Cursor extends Object implements ICursor {
    
    
    /** @var MongoCursor */
    private $cursor;
    
    
    public function __construct(MongoCursor $cursor, $options = array()) {
        $this->cursor = $cursor;
        /// options
    }
    
    
    public function orderBy($keys) {
        $this->getCursor()->sort($keys);
        return $this;
    }
    
    public function limit($limit, $offset = 0) {
        $this->getCursor()->limit($limit);
        if ($offset) $this->offset($offset);
        return $this;
    }
    
    public function offset($offset) {
        
        return $this;
    }
    
    /**
     * @return array
     */
    public function fetch() {
        $cursor = $this->getCursor();
        if (!$cursor->hasNext()) return NULL; 
        
        $item = $cursor->getNext();
        
        return Converter::mongoToPhongo($item);
    }
    
    /**
     * @return array<array>
     */    
    public function fetchAll() {
        $results = array();
        while ($item = $this->fetch()) {
            $results[] = $item;
        }
        return $results;
    }
    
    /**
     * @return MongoCursor
     */
    private function getCursor() {
        if (!$this->cursor) throw new InvalidStateException('No Cursor available.');
        
        return $this->cursor;
    }
    
}
