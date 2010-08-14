<?php

namespace Phongo;

use MongoCursor;
use InvalidStateException;


interface ICursor extends \Countable {
    public function __construct(MongoCursor $cursor, $options = array());
    public function order($keys);
    public function limit($limit, $offset = 0);
    public function offset($offset);
    public function fetch();
    public function fetchAll();
}


/**
 * Database query cursor (selecting, ordering, limits...)
 * 
 * hint
 * reset
 * info
 * fields
 * doQuery
 * dead
 */
class Cursor extends Object implements ICursor {
    
    
    /** @var MongoCursor */
    private $cursor;
    
    /** @var array */
    private $options = array();
    
    
    /**
     * options:
     *  - snapshotMode: FALSE (snapshot) - use snapshot mode for better result consistency
     *  - slaveOkay: FALSE - can use slave server to receive data from cursor
     *  - timeout: 20000 ms - client side cursor timeout
     *  - keepAlive: FALSE (immortal) - keep cursor alive on server even if client is not requesting data for a long time
     *  - tailable: FALSE - ability to read result from cursor even after the last result (if they are created later)
     */
    public function __construct(MongoCursor $cursor, $options = array()) {
        $this->cursor = $cursor;
        $this->setOptions($options);
    }
    
    /**
     * Set cursor options
     * @return self
     */
    public function setOptions($options = array()) {
        if (!empty($options['snapshotMode'])) $this->cursor->snapshot();
        $this->cursor->slaveOkay(empty($options['slaveOkay']) ? FALSE : TRUE);
        $this->cursor->timeout(empty($options['timeout']) ? 20000 : (int)$options['timeout']);
        $this->cursor->immortal(empty($options['keepAlive']) ? FALSE : TRUE);
        $this->cursor->tailable(empty($options['tailable']) ? FALSE : TRUE);
        $this->options = $options;
        return $this;
    }
    
    /**
     * @param array
     * @return self
     */
    public function order($keys) {
        $this->cursor->sort($keys);
        return $this;
    }
    
    /**
     * @param integer
     * @param integer
     * @return self
     */
    public function limit($limit, $offset = 0) {
        $this->cursor->limit($limit);
        if ($offset) $this->offset($offset);
        return $this;
    }
    
    /**
     * @param integer
     * @return self
     */
    public function offset($offset) {
        $this->cursor->skip($offset);
        return $this;
    }
    
    /**
     * @param bool
     * @return int
     */
    public function count($aplyLimit = FALSE) {
        return $this->cursor->count($aplyLimit);
    }
    
    /**
     * @return array
     */
    public function fetch() {
        if (!$this->cursor->hasNext()) return NULL;
        
        $item = $this->cursor->getNext();
        
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
    
}
