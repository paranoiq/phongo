<?php

namespace Phongo;

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


class Database extends Object implements IDatabase {
    
    
    /** @var IConnection */
    private $connection;
    
    /** @var MongoDB */
    private $database;
    /** @var string */
    private $name;
    
    /** @var integer replicate to n servers */
    private $safeMode = 0;
    /** @var bool sync to file before returning */
    private $fileSync = FALSE;
    
    /** @var bool */
    private $strictMode = FALSE;
    
    /** @var array cursor options */
    private $cursorOptions = array();
    
    /** @var DatabaseInfo */
    private $info;
    
    /** @var array<MongoCollection> */
    private $collections;
    
    /** @var string */
    private $namespace;
    /** @var string */
    private $collection;
    /** @var string */
    private $tempColl;
    
    /** @var integer */
    private $affectedItems = NULL;
    
    
    /**
     * @param MongoDB
     * @param string
     * @param array
     */
    public function __construct(IConnection $connection, MongoDB $database, $name, $options = array()) {
        $this->connection = $connection;
        $this->database = $database;
        $this->name = $name;
        ///
        
        $this->cursorOptions = array_intersect_key($options, array_flip(
            array('snapshotMode', 'slaveOkay', 'timeout', 'keepAlive', 'tailable')));
    }
    
    
    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    
    /** @return array */
    private function getOptions() {
        $options = array();
        if ($this->safeMode) $options['safe']  = version_compare(Mongo::VERSION, '1.0.9', '<') ? TRUE : $this->safeMode;
        if ($this->fileSync) $options['fsync'] = TRUE;
        return $options;
    }
    
    /** @return bool */
    public function isSync() {
        return $this->safeMode || $this->fileSync;
    }
    
    /** @param int */
    public function setSafeMode($numServers = 1) {
        $this->safeMode = (int)$numServers;
        return $this;
    }
    
    /** @param bool */
    public function setFileSync($fileSync = TRUE) {
        $this->fileSync = (bool)$fileSync;
        return $this;
    }
    
    /** @param bool */
    public function setStrictMode($strictMode = TRUE) {
        $this->strictMode = (bool)$strictMode;
        return $this;
    }
    
    /** @return DatabaseInfo */
    public function getInfo() {
        if (!$this->info) $this->info = new DatabaseInfo($this);
        return $this->info;
    }
    
    
    public function drop() {
        $this->checkResult($this->database->drop());
        $this->connection->releaseDatabase($this->name);
    }
    
    /**
     * @param bool
     * @param bool
     */
    public function repair($preserveClonedFiles = FALSE, $backupOriginalFiles = FALSE) {
        $this->checkResult($this->database->repair($preserveClonedFiles, $backupOriginalFiles));
        // Anti-WTF:: repair() deletes database if it's empty. re-create if strict mode is set
        if ($this->strictMode) $this->connection->createDatabase($this->name);
        
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
        
        if ($this->strictMode && !preg_match('/^(local|system|$cmd.sys)\./', $name) && !in_array($name, $this->getInfo()->getCollectionList())) 
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
     * Find objects. Returns a cursor
     * 
     * @param array|string
     * @param array
     * @param string
     * @return Phongo\ICursor
     */
    public function find($query = array(), $fields = array(), $collection = NULL) {
        if (!$fields) $fields = array();
        /*dump($fields);
        exit;*/
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $cursor = $this->getCollection($collection)->find($query, $fields);
        
        $class = $this->connection->getCursorClass();
        
        return new $class($cursor, $this->cursorOptions);;
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
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $result = $this->getCollection($collection)->findOne($query);
        
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
            $result = MongoDBRef::get($this->database, $reference->getMongoDBRef());
        } elseif (is_array($reference) && MongoDBRef::validate($reference)) {
            $result = MongoDBRef::get($this->database, $reference);
        } elseif (is_string($reference) && strlen($reference) == 24) {
            $collName = $this->determineCollectionName($collection);
            $ref = MongoDBRef::create($collName, $reference, $this->name);
            $result = MongoDBRef::get($this->database, $ref);
        } else {
            throw new \InvalidArgumentException('Invalid database reference.');
        }
        
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
        
        return $this->getCollection($collection)->count(array('count' => $query));
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
        $namespace = $this->name . '.' . $this->getCollection($collection)->getName();
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
        $options = $this->getOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $this->checkResult($this->getCollection($collection)->insert($object, $options), $this->isSync());
        
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
        $options = $this->getOptions();
        foreach ($objects as $id => $object) {
            if (!is_array($object)) $objects[$id] = Converter::jsonToMongo($object);
        }
        
        $this->checkResult($this->getCollection($collection)->batchInsert($objects, $options), $this->isSync());
        
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
        $options = $this->getOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $this->checkResult($this->getCollection($collection)->save($object, $options), $this->isSync());
        
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
        $options = $this->getOptions();
        if (!$single) $options['multiple'] = 1;
        if ($upsert) $options['upsert'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        if (!is_array($modifier)) $modifier = Converter::jsonToMongo($modifier);
        
        $result = $this->checkResult($this->getCollection($collection)->update($query, $modifier, $options), $this->isSync());
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
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
        $options = $this->getOptions();
        if ($single) $options['justOne'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $result = $this->checkResult($this->getCollection($collection)->remove($query, $options), $this->isSync());
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
        return $this->affectedItems;
    }
    
    
    /**
     * @param array|string PHP or JSON array
     * @param string
     * @return array command result
     */
    public function runCommand($command) {
        if (!is_array($command)) $command = Converter::jsonToMongo($command);
        
        return $this->checkResult($this->database->command($command));
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
        /// co když je akce provedena? sync? async?
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
     * @param bool
     * @param integer
     * @param integer               
     */
    public function createCollection($collection, $capped = FALSE, $size = 0, $maxItems = 0) {
        if (!Tools::validateCollectionName($collection))
            throw new \InvalidArgumentException("Collection name '$collection' is not valid.");
        
        $collection = $this->database->createCollection($collection, $capped, $size, $maxItems);
        if ($collection instanceof MongoCollection) {
            $this->collection = $collection->getName();
            $this->collections[$this->collection] = $collection;
        } else {
            $this->checkResult($collection);
        }
        
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
        $old = $this->name . '.' . $this->getCollection($collection)->getName();
        $new = ($newDatabase ?: $this->name) . '.' . $newCollection;
        
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
        if (empty($options['background']) && $this->safeMode) $options['safe'] = 1;
        
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
