<?php

namespace Phongo;

use Mongo;
use MongoDB;
use MongoCollection;
use MongoDBRef;


interface IDatabase {
    /*
    public function find();
    public function findOne();
    public function get();
    public function insert();
    public function save();
    public function update();
    public function delete();
    public function runCommand();
    */
}


class Database extends Base implements IDatabase {
    
    
    /** @var IConnection */
    private $connection;
    
    /** @var MongoDB */
    private $database;
    // DO NOT USE DIRECTLY! call $this->getName()
    /** @var string */
    private $name;
    
    /** @var array cursor options */
    private $cursorOptions = array();
    
    /** @var Phongo\DatabaseInfo */
    private $info;
    
    /** @var array<MongoCollection> */
    private $collections = array();
    
    /** @var string */
    private $namespace;
    /** @var string */
    private $collection;
    /** @var string */
    private $tempColl;
    
    /** @var integer */
    private $affectedItems = NULL;
    
    /** @var Phongo\Profiler */
    private $profiler;
    
    
    /**
     * @param MongoDB
     * @param string
     * @param array
     */
    public function __construct(IConnection $connection, MongoDB $database, $options = array()) {
        $this->connection = $connection;
        $this->database = $database;
        
        $this->options = $options;
        
        $this->cursorOptions = array_intersect_key($options, array_flip(
            array('snapshot', 'slaveOkay', 'timeout', 'keepAlive', 'tailable')));
        
        $this->profiler = $connection->getProfiler();
    }
    
    
    /** @return Phongo\Connection */
    public function getConnection() {
        return $this->connection;
    }
    
    /** @return Phongo\Cache */
    public function getCache() {
        return $this->connection->getCache();
    }
    
    /**
     * @return string
     */
    public function getName() {
        if (!isset($this->name)) {
            // MongoDB does not provide database name :[
            // call without profiler!
            $coll = $this->database->selectCollection('system.namespaces');
            $ns = $coll->findOne(array(), array());
            $this->name = substr($ns['name'], 0, strpos($ns['name'], '.'));
        }
        return $this->name;
    }
    
    public function isSafe() {
        if (isset($this->options['safe'])) return $this->options['safe'];
        if (isset($this->connection->options['safe'])) return $this->connection->options['safe'];
        return 0;
    }
    
    public function isFsync() {
        if (isset($this->options['fsync'])) return $this->options['fsync'];
        if (isset($this->connection->options['fsync'])) return $this->connection->options['fsync'];
        return FALSE;
    }
    
    public function isStrict() {
        if (isset($this->options['strict'])) return $this->options['strict'];
        if (isset($this->connection->options['strict'])) return $this->connection->options['strict'];
        return FALSE;
    }
    
    /** @return bool */
    public function isSync() {
        return $this->isSafe() || $this->isFsync();
    }
    
    /** @return array */
    private function getQueryOptions() {
        $options = array();
        if ($this->isSafe()) $options['safe']  = version_compare(Mongo::VERSION, '1.0.9', '<') ? TRUE : $this->isSafe();
        if ($this->isFsync()) $options['fsync'] = TRUE;
        return $options;
    }
    
    /** @return DatabaseInfo */
    public function getInfo() {
        if (!$this->info) $this->info = new DatabaseInfo($this);
        return $this->info;
    }
    
    
    public function drop() {
        $this->checkResult($this->database->drop());
        $this->connection->releaseDatabase($this->getName());
    }
    
    /**
     * @param bool
     * @param bool
     */
    public function repair($preserveClonedFiles = FALSE, $backupOriginalFiles = FALSE) {
        $this->checkResult($this->database->repair($preserveClonedFiles, $backupOriginalFiles));
        // Anti-WTF:: repair() deletes database if it's empty. re-create if strict mode is set
        if ($this->isStrict()) $this->connection->createDatabase($this->getName());
        
        return $this;
    }
    
    
    // -- COLLECTION SELECTION -----------------------------------------------------------------------------------------
    
    
    /**
     * Select namespace to use
     * @param string
     */
    public function useNamespace($namespace) {
        $this->namespace = $namespace;
        $this->tempColl = NULL;
        $this->collection = NULL;
        
        return $this;
    }
    
    /**
     * Select collection to use
     * @param string
     * @param string|NULL
     */
    public function useCollection($collection, $namespace = TRUE) {
        if ($namespace !== TRUE) $this->useNamespace($namespace);
        $this->tempColl = NULL;
        $this->collection = $collection;
        
        return $this;
    }
    
    /**
     * Select collection temporarily
     * @param string
     */
    public function collection($collection) {
        $this->tempColl = $collection;
        
        return $this;
    }
    
    /**
     * provides fluent namespaces: $db->name->space->collection->find();
     * @param string
     */
    public function &__get($name) {
        if (isset($this->tempColl)) {
            $this->tempColl .= '.' . $name;
        } else {
            $this->tempColl = $name;
        }
        
        return $this;
    }
    
    /**
     * @param string
     */
    private function getCollection($collection) {
        $name = $this->determineCollectionName($collection);
        
        if (!is_null($name)) {
            $coll = $this->database->selectCollection($name);
            $this->collections[$name] = $coll;
            return $coll;
        }
        
        if (isset($this->collections[$this->collection])) 
            return $this->collections[$this->collection];
        
        throw new \InvalidStateException('No collection selected!');
    }
    
    /**
     * @return string
     */
    private function determineCollectionName($collection = NULL) {
        if (isset($collection)) {
            $name = $collection;
            if (isset($this->tempColl)) 
                trigger_error("Collection '$this->tempColl' selected via collection() or __get() is not used. Check your logic!", E_USER_NOTICE);
        } elseif (isset($this->tempColl)) {
            $name = $this->tempColl;
        } elseif (isset($this->collection)) {
            $name = $this->collection;
        } else {
            throw new \InvalidStateException("No collection selected!");
        }
        
        if (isset($this->namespace)) $name = $this->namespace . '.' . $name;
        
        if (!Tools::validateCollectionName($collection, TRUE))
            throw new \InvalidArgumentException("Collection name '$collection' is not valid.");
        
        if ($this->isStrict() && !preg_match('/^(local|system|$cmd.sys)\./', $name) && !in_array($name, $this->getInfo()->getCollectionList())) 
            throw new DatabaseException("Collection '$name' does not exist!");
        
        $this->tempColl = NULL;
        return $name;
    }
    
    /**
     * @return string
     */
    public function getCollectionName() {
        return $this->getCollection()->getName();
    }
    
    
    // -- DATA QUERIES -------------------------------------------------------------------------------------------------
    
    
    /**
     * @param array|string PHP or JSON array
     * @param string
     * @return array command result
     */
    public function runCommand($command) {
        if (!is_array($command)) $command = Converter::jsonToMongo($command);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::COMMAND, $this->getName(), $command);
        
        $result = $this->checkResult($this->database->command($command));
        
        if (isset($ticket)) $this->profiler->after($ticket);
        
        return $result;
    }
    
    
    /**
     * Find objects. Returns a cursor
     * 
     * @param array|string
     * @param array
     * @param string
     * @return Phongo\ICursor
     */
    public function find($query = array(), $fields = array(), $collection = NULL) {
        if ($query instanceof Reference) return $this->get($query);
        
        if (!$fields) $fields = array();
        /*dump($fields);
        exit;*/
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $namespace = $this->getName() . '.' . $this->determineCollectionName($collection);
        $class = $this->connection->cursorClass;
        $cursor = new $class(
            $this->connection->mongo,
            $namespace,
            $query,
            $fields,
            $this->cursorOptions,
            $this->profiler);
        
        return $cursor;
    }
    
    
    /**
     * Find and return just one object
     * 
     * @param array|string
     * @param array
     * @param string
     * @return array found object
     */
    public function findOne($query = array(), $columns = array(), $collection = NULL) {
        if ($query instanceof Reference) return $this->get($query);
        
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::FINDONE, $this->getName() . '.' . $coll->getName(), $query);
        
        $result = $coll->findOne($query);
        
        if (isset($ticket)) $this->profiler->after($ticket, $result ? 1 : 0);
        
        /// validate!
        return $result;
    }
    
    
    /**
     * Get object by reference or id.
     * 
     * @param Reference|string|array
     * @param string
     * @return Phongo\ICursor
     */
    public function get($reference, $collection = NULL) {
        if ($reference instanceof Reference) {
            $reference = $reference->getMongoDBRef();
        } elseif (is_array($reference) && MongoDBRef::validate($reference)) {
            // OK
        } elseif (is_string($reference) && strlen($reference) == 24) {
            $collName = $this->determineCollectionName($collection);
            $reference = MongoDBRef::create($collName, $reference, $this->getName());
        } else {
            throw new \InvalidArgumentException('Invalid database reference.');
        }
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::GET, $this->getName() . '.' . $reference['$ref'], $reference);
        
        $result = MongoDBRef::get($this->database, $reference);
        
        if (isset($ticket)) $this->profiler->after($ticket, $result ? 1 : 0);
        
        /// validate!
        return $result;
    }
    
    
    /**
     * Returns count of matching objects
     * 
     * @param array|string
     * @param string
     * @return int
     */
    public function count($query = array(), $collection = NULL) {
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::FIND, $this->getName() . '.' . $coll->getName(), $reference);
        
        $count = $coll->count(array('count' => $query));
        
        if (isset($ticket)) $this->profiler->after($ticket, $count ?: 0);
        
        return $count;
    }
    
    
    /**
     * Returns data size of matching items
     * 
     * /// TODO: implement query
     * @param string
     * @param string
     * @param string
     * @return int
     */
    public function size($query = array(), $collection = NULL) {
        $namespace = $this->getName() . '.' . $this->getCollection($collection)->getName();
        /// query
        return $this->runCommand(array('dataSize' => $namespace));
    }
    
    
    /**
     * Insert a new object into collection (fails if it exists already)
     * 
     * @param array|string
     * @param string
     * @return array inserted object
     */
    public function insert($object, $collection = NULL) {
        $options = $this->getQueryOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::INSERT, $this->getName() . '.' . $coll->getName(), $object);
        
        try {
            $this->checkResult($coll->insert($object, $options), $this->isSync());
        } catch (\Exception $e) {
            if (isset($ticket)) $this->profiler->after($ticket, -1);
            throw $e;
        }
        
        if (isset($ticket)) $this->profiler->after($ticket, 1);
        
        return $object;
    }
    
    
    /**
     * Insert an array of new objects into collection (fails if they exist already)
     * 
     * @param array<array|string>
     * @param string
     * @return array<array> inserted objects
     */
    public function batchInsert($objects, $collection = NULL) {
        $options = $this->getQueryOptions();
        foreach ($objects as $id => $object) {
            if (!is_array($object)) $objects[$id] = Converter::jsonToMongo($object);
        }
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::INSERT, $this->getName() . '.' . $coll->getName(), $object);
        
        try {
            $this->checkResult($coll->batchInsert($objects, $options), $this->isSync());
        } catch (\Exception $e) {
            if (isset($ticket)) $this->profiler->after($ticket, -1);
            throw $e;
        }
        
        if (isset($ticket)) $this->profiler->after($ticket, -2); /// get count!
        
        return $objects;
    }
    
    
    /**
     * Save an object into collection (replace existing or insert a new one)
     * - does not support fileSync yet?
     * 
     * @param array|string
     * @param string
     * @return array saved object
     */
    public function save($object, $collection = NULL) {
        $options = $this->getQueryOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::SAVE, $this->getName() . '.' . $coll->getName(), $object);
        
        try {
            $this->checkResult($coll->save($object, $options), $this->isSync());
        } catch (\Exception $e) {
            if (isset($ticket)) $this->profiler->after($ticket, -1);
            throw $e;
        }
        
        if (isset($ticket)) $this->profiler->after($ticket, 1);
        
        return $object;
    }
    
    
    /**
     * Update existing items in collection or create a new one (upsert)
     * 
     * @param array|string
     * @param array|string
     * @param bool
     * @param bool
     * @param string
     * @return integer number of affected items     
     */
    public function update($query, $modifier, $single = FALSE, $upsert = FALSE, $collection = NULL) {
        $options = $this->getQueryOptions();
        if (!$single) $options['multiple'] = 1;
        if ($upsert) $options['upsert'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        if (!is_array($modifier)) $modifier = Converter::jsonToMongo($modifier);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::UPDATE, $this->getName() . '.' . $coll->getName(), $query);
        
        try {
            $result = $this->checkResult($coll->update($query, $modifier, $options), $this->isSync());
        } catch (\Exception $e) {
            if (isset($ticket)) $this->profiler->after($ticket, -1);
            $result = array();
            throw $e;
        }
        
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
        if (isset($ticket)) $this->profiler->after($ticket, $this->affectedItems ?: -2);
        
        return $this->affectedItems;
    }
    
    
    /**
     * Delete items from collection
     * 
     * @param array|sting
     * @param bool
     * @param string
     * @return integer number of affected items     
     */
    public function delete($query, $single = FALSE, $collection = NULL) {
        $options = $this->getQueryOptions();
        if ($single) $options['justOne'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $coll = $this->getCollection($collection);
        
        if ($this->profiler) 
            $ticket = $this->profiler->before($this, IProfiler::DELETE, $this->getName() . '.' . $coll->getName(), $query);
        
        try {
            $result = $this->checkResult($coll->remove($query, $options), $this->isSync());
        } catch (\Exception $e) {
            if (isset($ticket)) $this->profiler->after($ticket, -1);
            $result = array();
            throw $e;
        }
        
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
        if (isset($ticket)) $this->profiler->after($ticket, $this->affectedItems ?: -2);
        
        return $this->affectedItems;
    }
    
    
    /** @return integer */
    public function getAffectedItems() {
        return $this->affectedItems;
    }
    
    
    /**
     * @param array
     * @param bool 
     * @return array
     */
    private function checkResult($result, $type = Phongo::SYNC) {
        // special case when a request may return NULL (no action performed)
        /// co když je akce provedena? sync? async? WTF! bug!
        if ($type === Phongo::IGNORE && $result === NULL) return;
        
        if ($type === Phongo::SYNC) {
            $error = $result;
        } elseif ($type === Phongo::ASYNC && $this->isSync()) {
            $error = $this->lastDatabase->lastError();
        } else {
            // check only return value
            if (!$result) 
                throw new DatabaseException('Asynchronous request failed on sending.', $result);
            return $result;
        }
        
        if (!isset($error['ok']) || !$error['ok']) {
            dump($result);
            exit;
            throw new DatabaseException($error['err'], $result);
        }
        
        return $result;
    }
    
    
    // -- COLLECTIONS --------------------------------------------------------------------------------------------------
    
    
    /**
     * @param string
     * @param array options (capped, size, max, autoIndexId)
     * - capped: bool, fixed size collection
     * - size: integer, prealocated space for collection (maximal size for capped collection)
     * - max: integer, maximum number of items in capped collection
     * - autoIndexId: bool, create automatic index on key `_id` (default is TRUE)
     */
    public function createCollection($collection, $options = array()) {
        if (!Tools::validateCollectionName($collection))
            throw new \InvalidArgumentException("Collection name '$collection' is not valid.");
        
        $query = array_merge(array('create' => $collection), $options);
        
        $this->checkResult($this->runCommand($query));
        $this->collection = $collection;
        
        return $this;
    }
    
    /**
     * @param string
     * @param bool
     */
    public function validateCollection($collection = NULL, $validateData = FALSE) {
        $this->checkResult($this->getCollection($collection)->validate($validateData = FALSE));
        
        return $this;
    }
    
    /** @param string */
    public function dropCollection($collection = NULL) {
        $this->checkResult($this->getCollection($collection)->drop());
        
        return $this;
    }
    
    /** @param string */
    public function emptyCollection($collection = NULL) {
        $this->delete(array(), FALSE, $collection);
        /// drop + create?
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     * @param string
     */
    public function renameCollection($newCollection, $newDatabase = NULL, $collection = NULL) {
        $old = $this->getName() . '.' . $this->getCollection($collection)->getName();
        $new = ($newDatabase ?: $this->getName()) . '.' . $newCollection;
        
        $this->connection->database('admin')->runCommand(array('renameCollection' => $old, 'to' => $new));
        
        return $this;
    }
    
    
    // -- INDEXES ------------------------------------------------------------------------------------------------------
    
    
    /**
     * @param string
     * @param array
     * @param string
     * @param string
     */
    public function createIndex($keys, $options = array(), $collection = NULL) {
        if (empty($options['background']) && $this->isSafe()) $options['safe'] = 1;
        
        $result = $this->getCollection($collection)->ensureIndex($keys, $options);
        $this->checkResult($result, isset($options['safe']));
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function dropIndex($indexName, $collection = NULL) {
        // MongoCollection::deleteIndex() is buggy!
        $this->runCommand(array(
            'dropIndexes' => $this->getCollection($collection)->getName(), 
            'index' => $indexName));
        
        return $this;
    }
    
    /** @param string */
    public function dropIndexes($collection = NULL) {
        $this->checkResult($this->getCollection($collection)->deleteIndexes());
        
        return $this;
    }
    
    /** @param string */
    public function reindexCollection($collection = NULL) {
        $this->runCommand(array('reIndex' => $this->getCollection($collection)->getName()));
        
        return $this;
    }
    
}
