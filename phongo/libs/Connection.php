<?php

namespace Phongo;

use Mongo;
use MongoCollection;
use MongoConnectionException;

use InvalidStateException;
use InvalidArgumentException;


// check PHP version
if (version_compare(PHP_VERSION, '5.3.0', '<')) 
	throw new Exception('Phongo needs PHP 5.3.0 or newer.');
// check Mongo extension
if (!class_exists('Mongo'))
    throw new Exception('Mongo extension for PHP is not installed.');
// check Mongo extension version
if (version_compare(Mongo::VERSION, '1.0.5', '<')) 
	throw new Exception('Phongo needs Mongo extension 1.0.5 or newer.');


/** formal Connection interface */
interface IConnection {
    /*
    public function connect();
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


/**
 * MongoDB driver
 * 
 * 1.0.5 Added "safe" option.
 * 1.0.9 Added ability to pass integers to "safe" options (only accepted booleans before) and added "fsync" option.
 * $w functionality is only available in version 1.5.1+ of the MongoDB server and 1.0.8+ of the driver
 */
class Connection extends Object implements IConnection {
    
    /**#@+ request result behavior */
    const SYNC   = TRUE;
    const ASYNC  = FALSE;
    const IGNORE = NULL;
    /**#@-*/
    
    
    /** @var string */
    private $cursorClass = 'Phongo\Cursor';
    
    /** @var array */
    private $servers = array();
    /** @var string */
    private $user;
    /** @var string */
    private $password;
    
    /** @var Mongo */
    private $mongo;
    /** @var MongoDB */
    private $database;
    /** @var string */
    private $databaseName;
    /** @var MongoCollection */
    private $collection;
    /** @var Phongo\ICursor */
    private $cursor;
    /** @var integer */
    private $affectedItems;
    
    /** @var integer replicate to n servers */
    private $safeMode = 0;
    /** @var bool sync to file before returning */
    private $fileSync = FALSE;
    /** @var MongoDB used in safe mode for checking results of asynchronous requests */
    private $lastDatabase;
    
    /** @var bool */
    private $strictMode = FALSE;
    
    /** @var DatabaseInfo */
    private $info;
    
    
    /**
     * Params:
     *  - servers ?
     *  - user
     *  - password
     *  
     *  - safeMode: 0 (safe) - wait for replication on x servers when making an insert/update/delete. immediate return othervise
     *  - fileSync: FALSE (fsync) - force filesync before returning on an insert/update/delete action
     *  - masterOnly: FALSE (slaveOkay) - use only master server to receive data from cursor
     *  - timeout: 20000 ms (timeout) - client side cursor timeout
     *  - keepAlive: FALSE (immortal) - keep cursor alive on server even if client is not requesting data for a long time
     *  - tailable: FALSE - ability to read result from cursor even after the last result (if they are created later)
     *  
     *  - strictMode: FALSE - do not allow to write in non-existing databases and collections
     *  - profiler ?
     *          
     * @param string|array
     * @param string
     */
    public function __construct($servers = NULL, $params = array()) {
        if (!$servers) {
            $this->servers[] = ini_get("mongo.default_host") . ':' . ini_get("mongo.default_port");
        } elseif (is_array($servers)) {
            $this->servers = $servers;
        } else {
            $this->servers[] = $servers;
        }
        //$this->user = $user;
        //$this->password = $password;
    }
    
    
    /** @param string */
    public function setCursorClass($class) {
        if (!in_array('Phongo\ICursor', class_implements($class, /*autoload*/TRUE))) 
            throw new InvalidArgumentException('Cursor class must implement interface Phongo\ICursor.');
        
        $this->cursorClass = $class;
        return $this;
    }
    
    /** @param bool */
    public function setStrictMode($strictMode = TRUE) {
        $this->strictMode = (bool)$strictMode;
        return $this;
    }
    
    /** @param bool */
    public function setSafeMode($numServers = 1) {
        $this->safeMode = (int)$numServers;
        return $this;
    }
    
    /** @param bool */
    public function setFileSync($fileSync = TRUE) {
        $this->fileSync = (bool)$fileSync;
        return $this;
    }
    
    /** @return bool */
    public function isSync() {
        return $this->safeMode || $this->fileSync;
    }
    
    /** @return array */
    private function getOptions() {
        $options = array();
        if ($this->safeMode) $options['safe']  = version_compare(Mongo::VERSION, '1.0.9', '<') ? TRUE : $this->safeMode;
        if ($this->fileSync) $options['fsync'] = TRUE;
        return $options;
    }
    
    /** @return array */
    public function getServers() {
        return $this->servers;
    }
    
    /** @return DatabaseInfo */
    public function getInfo() {
        if (!$this->info) $this->info = new DatabaseInfo($this);
        return $this->info;
    }
    
    
    // -- CONNECTION ---------------------------------------------------------------------------------------------------
    
    
    /** @return bool */
    public function connect() {
        $dsn = 'mongodb://';
        $dsn .= implode(',', $this->servers);
        if ($this->user) {
            $dsn .= $this->user;
            if ($this->password) $dsn .= ':' . $this->password;
            $dsn .= '@';
        }
        
        try {
            $this->mongo = new Mongo($dsn, array("connect" => TRUE));
            $this->mongo->connect();
		} catch (MongoConnectionException $e) {
            throw new DatabaseException($e->getMessage(), $dsn);
        }
        
        return $this;
    }
    
    /// ???
    public function authenticate($user, $password, $database = NULL) {
        ///
    }
    
    // logout
    
    // ping
    
    /** @return bool */
    public function isMaster() {
        $result = $this->runCommand(array('isMaster' => 1), 'admin');
        return (bool)$result['ismaster'];
    }
    
    /** @param array */
    public function shutdownServer() {
        return $this->runCommand(array('shutdown' => 1), 'admin');
    }
    
    /** @param array */
    public function lockWrite() {
        return $this->runCommand(array('fsync' => 1, 'lock' => 1), 'admin');
    }
    
    /** @param array */
    public function unlockWrite() {
        return $this->findOne(array(), array(), '$cmd.sys.unlock', 'admin');
    }
    
    /** @return bool */
    public function isLocked() {
        $result = $this->findOne(array(), array(), '$cmd.sys.inprog', 'admin');
        return !empty($result['fsyncLock']);
    }
    
    /** @return array<array> */
    public function getOperationList() {
        $result = $this->findOne(array('$all' => 1), array(), '$cmd.sys.inprog', 'admin');
        return $result['inprog'];
    }
    
    /** @return array */
    public function terminateOperation($operationId) {
        return $this->findOne(array('op' => (int)$operationId), array(), '$cmd.sys.killop', 'admin');
    }
    
    
    // -- RESOURCES ----------------------------------------------------------------------------------------------------
    
    
    /**
     * @param string
     * @param bool
     */
    private function getDatabase($database) {
        if ($this->strictMode && !is_null($database) && !in_array($database, $this->getDatabaseList())) 
            throw new StructureException("Database '$database' is not created!");
        
        if (!is_null($database)) {
            if (!preg_match("/^[-!#%&'()+,0-9;>=<@A-Z\[\]^_`a-z{}~]+$/", $database))
                throw new InvalidArgumentException('Invalid character in database name.');
            return $this->mongo->selectDB($database);
        }
        
        if ($this->database) 
            return $this->database;
        
        throw new InvalidStateException('No database selected!');
    }
    
    /**
     * @param string
     * @param string
     */
    private function getCollection($collection, $database) {
        if ($this->strictMode && !is_null($collection) && !preg_match('^(system|$cmd)\.', $collection)
            && !in_array($collection, $this->getCollectionList($database))) 
            throw new StructureException("Collection '$collection' is not created!");
        
        if (!is_null($collection)) 
            return $this->getDatabase($database)->selectCollection($collection);
        
        if ($this->collection) 
            return $this->collection;
        
        throw new InvalidStateException('No collection selected!');
    }
    
    /**
     * @param array
     * @param bool 
     * @return array
     */
    private function checkResult($result, $type = self::SYNC) {
        // special case when a request may return NULL (no action performed)
        /// co když je akce provedena? sync? async?
        if ($type === self::IGNORE && $result === NULL) return;
        
        if ($type === self::SYNC) {
            $error = $result;
        } elseif ($type === self::ASYNC && $this->isSync()) {
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
    
    
    // -- DATA QUERIES -------------------------------------------------------------------------------------------------
    
    
    // throw MongoCursorException on safeMode fail
    // throw MongoCursorTimeoutException on safeMode timeout
    
    
    /**
     * Find objects. Returns a cursor
     *          
     * @param array|string
     * @param array
     * @param string
     * @param string
     * @return Phongo\ICursor
     */
    public function find($query = array(), $columns = array(), $collection = NULL, $database = NULL) {
        //if (!$columns) $columns = array();
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $cursor = $this->getCollection($collection, $database)->find($query);
        
        $this->cursor = new $this->cursorClass($cursor);
        return $this->cursor;
    }
    
    
    /** @return Phongo\ICursor */
    public function getCursor() {
        if (!$this->cursor) 
            throw new InvalidStateException('No cursor available.');
        
        return $this->cursor;
    }
    
    
    /**
     * Find and return just one object
     * 
     * @param array|string
     * @param array
     * @param string
     * @param string
     * @return array found object
     */
    public function findOne($query = array(), $columns = array(), $collection = NULL, $database = NULL) {
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        $result = $this->getCollection($collection, $database)->findOne($query);
        
        /// validate!
        return $result;
    }
    
    
    /**
     * Get object by reference or id.
     *          
     * @param Reference|string
     * @param string
     * @param string
     * @return Phongo\ICursor
     */
    public function get($reference, $collection = NULL, $database = NULL) {
        if ($reference instanceof Reference) {
            $result = MongoDBRef::get($this->getDatabase($database), $reference->getReference());
        } else {
            $ref = MongoDBRef::create($collection, $reference, $database);
            $result = MongoDBRef::get($this->getDatabase($database), $ref);
        }
        
        /// validate!
        return $result;
    }
    
    
    /**
     * Returns count of matching objects
     * 
     * @param array|string
     * @param string
     * @param string
     * @return int
     */
    public function count($query = array(), $collection = NULL, $database = NULL) {
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        return $this->getCollection($collection, $database)->count(array('count' => $query));
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
    public function size($query = array(), $collection = NULL, $database = NULL) {
        $coll = ($database ?: $this->databaseName) . '.' . $this->getCollection($collection, $database)->getName();
        /// query
        return $this->runCommand(array('dataSize' => $coll), $database);
    }
    
    
    /**
     * Insert a new object into collection (fails if it exists already)
     * 
     * @param array|string
     * @param string
     * @param string
     * @return array inserted object
     */
    public function insert($object, $collection = NULL, $database = NULL) {
        $options = $this->getOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $this->checkResult($this->getCollection($collection, $database)->insert($object, $options), $this->isSync());
        
        return $object;
    }
    
    
    /**
     * Insert an array of new objects into collection (fails if they exist already)
     * 
     * @param array<array|string>
     * @param string
     * @param string
     * @return array<array> inserted objects     
     */
    public function batchInsert($objects, $collection = NULL, $database = NULL) {
        $options = $this->getOptions();
        foreach ($objects as $id => $object) {
            if (!is_array($object)) $objects[$id] = Converter::jsonToMongo($object);
        }
        
        $this->checkResult($this->getCollection($collection, $database)->batchInsert($objects, $options), $this->isSync());
        
        return $objects;
    }
    
    
    /**
     * Save an object into collection (replace existing or insert a new one)
     * - does not support fileSync yet?
     * 
     * @param array|string
     * @param string
     * @param string
     * @return array saved object     
     */
    public function save($object, $collection = NULL, $database = NULL) {
        $options = $this->getOptions();
        if (!is_array($object)) $object = Converter::jsonToMongo($object);
        
        $this->checkResult($this->getCollection($collection, $database)->save($object, $options), $this->isSync());
        
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
     * @param string
     * @return integer number of affected items     
     */
    public function update($query, $modifier, $single = FALSE, $upsert = FALSE, $collection = NULL, $database = NULL) {
        $options = $this->getOptions();
        if (!$single) $options['multiple'] = 1;
        if ($upsert) $options['upsert'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        if (!is_array($modifier)) $modifier = Converter::jsonToMongo($modifier);
        
        //dump($query);
        
        $result = $this->checkResult($this->getCollection($collection, $database)->update($query, $modifier, $options), $this->isSync());
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
        return $this->affectedItems;
    }
    
    
    /**
     * Delete items from collection
     * 
     * @param array|sting
     * @param bool
     * @param string
     * @param string
     * @return integer number of affected items     
     */
    public function delete($query, $single = FALSE, $collection = NULL, $database = NULL) {
        $options = $this->getOptions();
        if ($single) $options['justOne'] = 1;
        if (!is_array($query)) $query = Converter::jsonToMongo($query);
        
        dump($query);
        
        $result = $this->checkResult($this->getCollection($collection, $database)->remove($query, $options), $this->isSync());
        $this->affectedItems = isset($result['n']) ? $result['n'] : NULL;
        
        return $this->affectedItems;
    }
    
    
    /**
     * @param array|string PHP or JSON array
     * @param string
     * @return array command result
     */
    public function runCommand($command, $database = NULL) {
        if (!is_array($command)) $command = Converter::jsonToMongo($command);
        
        return $this->checkResult($this->getDatabase($database)->command($command));
    }
    
    
    /** @return integer */
    public function getAffectedItems() {
        return $this->affectedItems;
    }
    
    
    // -- DATABASES ----------------------------------------------------------------------------------------------------
    
    
    /**
     * @return string
     */
    public function getDatabaseName() {
        if (!$this->database)
            throw new InvalidStateException('No database selected.');
        return $this->databaseName;
    }
    
    /** @param string */
    public function selectDatabase($database) {
        $this->database = $this->getDatabase($database);
        $this->lastDatabase = $this->database;
        $this->databaseName = $database;
        
        $this->collection = NULL;
        $this->cursor = NULL;
        
        return $this;
    }
    
    /** @param string */
    public function createDatabase($database) {
        // Anti-WTF: empty database can be created only by writing to it
        $this->runCommand(array('create' => 'tristatricettristribrnychstrikacek'), $database);
        $this->runCommand(array('drop'   => 'tristatricettristribrnychstrikacek'), $database);
        
        $this->database = $this->selectDatabase($database);
        
        return $this;
    }
    
    /** @param string */
    public function dropDatabase($database) {
        $this->checkResult($this->getDatabase($database)->drop());
        
        return $this;
    }
    
    /** 
     * @param string
     * @param bool
     * @param bool
     */
    public function repairDatabase($database, $preserveClonedFiles = FALSE, $backupOriginalFiles = FALSE) {
        $this->checkResult($this->getDatabase($database)->repair($preserveClonedFiles, $backupOriginalFiles));
        // Anti-WTF:: repair() deletes database if it's empty. re-create if strict mode is set
        if ($this->strictMode) $this->createDatabase($database);
        
        return $this;
    }
    
    
    // -- COLLECTIONS --------------------------------------------------------------------------------------------------
    
    
    /**
     * @return string
     */
    public function getCollectionName() {
        if (!$this->collection) 
            throw new InvalidStateException('No collection selected.');
        return $this->collection->getName();
    }
    
    /**
     * @param string
     * @param string
     */
    public function selectCollection($collection, $database = NULL) {
        $this->collection = $this->getCollection($collection, $database);
        $this->cursor = NULL;
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     * @param bool
     * @param integer
     * @param integer               
     */
    public function createCollection($collection = NULL, $database = NULL, $capped = FALSE, $size = 0, $maxItems = 0) {
        $collection = $this->getDatabase($database)->createCollection($collection, $capped, $size, $maxItems);
        if (!($collection instanceof MongoCollection)) $this->checkResult($collection);
        
        $this->database = $this->getDatabase($database);
        $this->collection = $collection;
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     * @param bool     
     */
    public function validateCollection($collection = NULL, $database = NULL, $validateData = FALSE) {
        $this->checkResult($this->getCollection($collection, $database)->validate($validateData = FALSE));
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function dropCollection($collection = NULL, $database = NULL) {
        $this->checkResult($this->getCollection($collection, $database)->drop());
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function emptyCollection($collection = NULL, $database = NULL) {
        $this->delete(array(), FALSE, $collection, $database);
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function renameCollection($newCollection, $collection = NULL, $newDatabase = NULL, $database = NULL) {
        $old = ($database ?: $this->databaseName) . '.' . $this->getCollection($collection, $database)->getName();
        $new = ($newDatabase ?: $this->databaseName) . '.' . $newCollection;
        
        $this->runCommand(array('renameCollection' => $old, 'to' => $new), 'admin');
        
        return $this;
    }
    
    
    // -- INDEXES ------------------------------------------------------------------------------------------------------
    
    
    /**
     * @param string
     * @param array
     * @param string
     * @param string
     */
    public function createIndex($keys, $options = array(), $collection = NULL, $database = NULL) {
        if (empty($options['background']) && $this->safeMode) $options['safe'] = 1;
        
        $result = $this->getCollection($collection, $database)->ensureIndex($keys, $options);
        $this->checkResult($result, isset($options['safe']));
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     * @param string
     */
    public function dropIndex($indexName, $collection = NULL, $database = NULL) {
        // MongoCollection::deleteIndex() is buggy!
        $this->runCommand(array(
            'dropIndexes' => $this->getCollection($collection, $database)->getName(), 
            'index' => $indexName), $database);
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function dropIndexes($collection = NULL, $database = NULL) {
        $this->checkResult($this->getCollection($collection, $database)->deleteIndexes());
        
        return $this;
    }
    
    /**
     * @param string
     * @param string
     */
    public function reindexCollection($collection = NULL, $database = NULL) {
        $this->runCommand(array('reIndex' => $this->getCollection($collection, $database)->getName()), $database);
        
        return $this;
    }
    
    
    // -- PROCESSES ----------------------------------------------------------------------------------------------------
    
    
    public function getProcessList() {
        //$list = $this->runCommand(array('currentOp' => 1), 'admin');
        $list = $this->findOne(array(), array(), '$cmd.sys.inprog', 'admin');
        
        $list = $list['inprog'];
        
        dump($list);
    }
    
    public function terminateProcess($processId) {
        ///
    }
    
}
