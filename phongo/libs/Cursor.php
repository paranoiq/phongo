<?php

namespace Phongo;

use Mongo;
use MongoCursor;


interface ICursor extends \Countable {
    public function __construct(Mongo $mongo, $namespace = NULL, $query = NULL, $fields = array(), $options = array());
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
 * dead
 * explain 
 */
class Cursor extends Object implements ICursor {
    
    
    /** @var Mongo */
    private $mongo;
    /** @var MongoCursor */
    private $cursor;
    
    /** @var array */
    private $options = array();
    
    /** @var string */
    private $namespace;
    /** @var array */
    private $query;
    /** @var array */
    private $fields;
    /** @var integer */
    private $order;
    /** @var integer */
    private $limit;
    /** @var integer */
    private $offset;
    
    /** @var Phongo\Profiler */
    private $profiler;
    /** @var integer */
    private $profilerTicket;
    
    /** @var bool */
    private $started = FALSE;
    
    
    /**
     * options:
     *  - snapshotMode: FALSE (snapshot) - use snapshot mode for better result consistency
     *  - slaveOkay: FALSE - can use slave server to receive data from cursor
     *  - timeout: 20000 ms - client side cursor timeout
     *  - keepAlive: FALSE (immortal) - keep cursor alive on server even if client is not requesting data for a long time
     *  - tailable: FALSE - ability to read result from cursor even after the last result (if they are created later)
     */
    public function __construct(Mongo $mongo, $namespace = NULL, $query = NULL, $fields = array(), 
        $options = array(), Profiler $profiler = NULL) {
        
        $this->mongo = $mongo;
        $this->namespace = $namespace;
        $this->query = $query;
        $this->fields = $fields;
        $this->options = $options;
        
        $this->cursor = new MongoCursor($mongo, $namespace, $query, $fields);
        
        $this->setOptions($options);
        
        $this->profiler = $profiler;
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
        $this->order = $keys;
        return $this;
    }
    
    
    /**
     * @param integer
     * @param integer
     * @return self
     */
    public function limit($limit, $offset = 0) {
        // Anti-WTF: force user to always set limit before calling count()
        if ($this->started)
            throw new \InvalidStateException('Limit must be set before cursor execution!');
        
        $this->limit = $limit;
        $this->cursor->limit($limit);
        if ($offset) $this->offset($offset);
        
        return $this;
    }
    
    
    /**
     * @param integer
     * @return self
     */
    public function offset($offset) {
        $this->offset = $offset;
        $this->cursor->skip($offset);
        return $this;
    }
    
    
    // -- RESULT AND FETCHING ------------------------------------------------------------------------------------------
    
    
    /**
     * Count returned items or matched items
     * @param bool
     * @return int
     */
    public function count($returned = TRUE) {
        if (!$this->started) $this->start();
        
        // MongoCursor::count() is buggy - count(TRUE) returns 0 if no conditions was given
        if ($returned && !empty($this->limit)) {
            $count = $this->cursor->count(TRUE);
        } else {
            $count = $this->cursor->count(FALSE);
        }
        
        if (!$this->started) $this->finish();
        
        return $count;
    }
    
    
    /**
     * @return array
     */
    public function fetch() {
        if (!$this->started) $this->start();
        
        if (!$this->cursor->hasNext()) {
            if (!$this->started) $this->finish();
            return NULL;
        }
        
        if (!$this->started) $this->finish();
        
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
    
    
    // -- PROFILER AND EXPLAIN -----------------------------------------------------------------------------------------
    
    
    /**
     * Explain query execution plan
     * @param bool  preserve the actual query result (clone the cursor)
     * @return string
     */
    public function explain($safe = FALSE) {
        if ($safe) {
            $clone = clone $this;
            return $clone->explain();
        } else {
            return $this->cursor->explain();
        }
    }
    
    
    /** Profiler before */
    private function start() {
        /// timer
        
        if (!$this->profiler) return;
        $this->profilerTicket = $this->profiler->before(NULL, IProfiler::FIND, $this->namespace, $this->query);
    }
    
    
    /** Profiler after */
    private function finish() {
        /// timer
        $this->started = TRUE;
        
        if (!$this->profiler) return;
        $this->profiler->after($this->profilerTicket, $this);
    }
    
    
    /** Prepare new cursor for re-execution */
    private function __clone() {
        $this->started = FALSE;
        $this->profiler = NULL;
        $this->cursor = new MongoCursor($this->mongo, $this->namespace, $this->query, $this->fields);
        $this->setOptions($this->options);
        if ($this->order) $this->order($this->order);
        if ($this->limit) $this->limit($this->limit);
        if ($this->offset) $this->offset($this->offset);
    }
    
}
