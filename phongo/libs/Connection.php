<?php

namespace Phongo;

use Mongo;
use MongoConnectionException;


// check PHP version
if (version_compare(PHP_VERSION, '5.3.0', '<')) 
	throw new \Exception('Phongo needs PHP 5.3.0 or newer.');
// check Mongo extension
if (!class_exists('Mongo'))
    throw new \Exception('Mongo extension for PHP is not installed.');
// check Mongo extension version
if (version_compare(Mongo::VERSION, '1.0.5', '<')) 
	throw new \Exception('Phongo needs Mongo extension 1.0.5 or newer.');


/** formal Connection interface */
interface IConnection {
    /*
    public function connect();
    public function database($database);
    public function __get($name);
    */
}

abstract class Base extends Object {
    
    /** @var Mongo */
    protected $mongo;
    
    /** @var string */
    protected $cursorClass = 'Phongo\Cursor';
    
    /** @var array database options */
    protected $options = array();
    
    
    /** @param string */
    public function setCursorClass($class) {
        if (!in_array('Phongo\ICursor', class_implements($class, /*autoload*/TRUE))) 
            throw new \InvalidArgumentException('Cursor class must implement interface Phongo\ICursor.');
        
        $this->cursorClass = $class;
        return $this;
    }
    
    /** @param int */
    public function setSafe($numServers = 1) {
        $this->options['safe'] = (int)$numServers;
        return $this;
    }
    
    /** @param bool */
    public function setFsync($fsync = TRUE) {
        $this->options['fsync'] = (bool)$fsync;
        return $this;
    }
    
    /** @param bool */
    public function setStrict($strict = TRUE) {
        $this->options['strict'] = (bool)$strict;
        return $this;
    }
    
}


/**
 * MongoDB driver
 * 
 * 1.0.5 Added "safe" option.
 * 1.0.9 Added ability to pass integers to "safe" options (only accepted booleans before) and added "fsync" option.
 * $w functionality is only available in version 1.5.1+ of the MongoDB server and 1.0.8+ of the driver
 */
class Connection extends Base implements IConnection {
    
    /** @var bool*/
    private $connected = FALSE;
    /** @var array */
    private $servers = array();
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    
    /** @var array<Phongo\IDatabase> active database drivers */
    private $databases = array();
    
    /** @var Phongo\ConnectionInfo */
    private $info;
    
    /** @var Phongo\Cache */
    private $cache;
    
    /** @var Phongo\Profiler */
    private $profiler;
    
    
    /**
     * options:
     *  - servers: array()
     *  - username
     *  - password
     *  
     *  - safeMode: 0 (safe) - wait for replication on x servers when making an insert/update/delete. immediate return othervise
     *  - fileSync: FALSE (fsync) - force filesync before returning on an insert/update/delete action
     *  
     *  - snapshotMode: FALSE (snapshot) - use snapshot mode for better result consistency
     *  - slaveOkay: FALSE - can use slave server to receive data from cursor
     *  - timeout: 20000 ms - client side cursor timeout
     *  - keepAlive: FALSE (immortal) - keep cursor alive on server even if client is not requesting data for a long time
     *  - tailable: FALSE - ability to read result from cursor even after the last result (if they are created later)
     *  
     *  - strictMode: FALSE - do not allow to write in non-existing databases and collections
     *  - profiler ?
     *          
     * @param string|array
     * @param string
     */
    public function __construct($options = array()) {
        if (empty($options['servers'])) {
            $this->servers[] = ini_get("mongo.default_host") . ':' . ini_get("mongo.default_port");
        } elseif (is_array($options['servers'])) {
            $this->servers = $options['servers'];
        } else {
            $this->servers[] = $options['servers'];
        }
        
        //$this->username = $options['username'];
        //$this->password = $options['password'];
        
        //$profiler
        
        unset($options['servers']);
        $this->options = $options;
    }
    
    
    /** @return Phongo\DatabaseInfo */
    public function getInfo() {
        if (!$this->info) $this->info = new ConnectionInfo($this);
        return $this->info;
    }
    
    /** @return Phongo\Cache */
    public function getCache() {
        if (!$this->cache) $this->cache = new Cache;
        return $this->cache;
    }
    
    /** @return Phongo\IProfiler */
    public function getProfiler() {
        return $this->profiler;
    }
    
    /** @param Phongo\IProfiler */
    public function setProfiler(IProfiler $profiler) {
        $this->profiler = $profiler;
    }
    
    
    // -- CONNECTION ---------------------------------------------------------------------------------------------------
    
    
    /** @return bool */
    public function connect() {
        if ($this->connected) return $this;
        
        $dsn = 'mongodb://';
        $dsn .= implode(',', $this->servers);
        if ($this->username) {
            $dsn .= $this->username;
            if ($this->password) $dsn .= ':' . $this->password;
            $dsn .= '@';
        }
        
        if ($this->profiler) $ticket = $this->profiler->before($this, IProfiler::CONNECT);
        
        try {
            $this->mongo = new Mongo($dsn, array("connect" => TRUE));
            $this->mongo->connect();
		} catch (MongoConnectionException $e) {
            throw new DatabaseException($e->getMessage(), $dsn);
        }
        
        if (isset($ticket)) $this->profiler->after($ticket);
        
        return $this;
    }
    
    /// ???
    public function authenticate($user, $password, $database = NULL) {
        ///
    }
    
    // logout
    
    // ping
    
    /** @return array */
    public function getServers() {
        return $this->servers;
    }
    
    /** @return bool */
    public function isMaster() {
        $result = $this->database('admin')->runCommand(array('isMaster' => 1));
        return (bool)$result['ismaster'];
    }
    
    /** @param array */
    public function shutdownServer() {
        return $this->database('admin')->runCommand(array('shutdown' => 1));
    }
    
    /** @param array */
    public function lockWrite() {
        return $this->database('admin')->runCommand(array('fsync' => 1, 'lock' => 1));
    }
    
    /** @param array */
    public function unlockWrite() {
        return $this->database('admin')->findOne(array(), array(), '$cmd.sys.unlock');
    }
    
    /** @return bool */
    public function isLocked() {
        $result = $this->database('admin')->findOne(array(), array(), '$cmd.sys.inprog');
        return !empty($result['fsyncLock']);
    }
    
    
    // -- DATABASES ----------------------------------------------------------------------------------------------------
    
    
    /**
     * @param string
     * @param bool
     */
    public function database($database) {
        if (isset($this->databases[$database])) return $this->databases[$database];
        
        if (!empty($this->options['strict']) && !is_null($database) && !in_array($database, $this->getDatabaseList())) 
            throw new DatabaseException("Database '$database' is not created!");
        
        if (!Tools::validateDatabaseName($database))
            throw new \InvalidArgumentException("Database name '$database' is not valid.");
        
        $db = new Database($this, $this->mongo->selectDB($database), $this->options, $database);
        $this->databases[$database] = $db;
        
        return $db;
    }
    
    /**
     * @param string
     * @return Phongo\IDatabase
     */
    public function &__get($name) {
        $db = $this->database($name);
        return $db;
    }
    
    /** @param string */
    public function releaseDatabase($database) {
        unset($this->databases[$database]);
        return $this;
    }
    
    /** @param string */
    public function createDatabase($database) {
        if (!Tools::validateDatabaseName($database))
            throw new \InvalidArgumentException("Database name '$database' is not valid.");
        
        // Anti-WTF: empty database can be created only by writing to it
        $db = $this->database($database);
        $db->runCommand(array('create' => 'tristatricettristribrnychstrikacek'));
        $db->runCommand(array('drop'   => 'tristatricettristribrnychstrikacek'));
        
        return $this;
    }
    
    
    // -- OTHER --------------------------------------------------------------------------------------------------------
    
    
    /**
     * @param Phongo\Reference
     * @return array
     */
    public function get(Reference $ref) {
        return $this->getDatabase($ref->database)->get($ref);
    }
    
    
    /** @return array<array> */
    public function getProcessList($all = FALSE) {
        $result = $this->database('admin')->findOne(array('$all' => $all ? 1 : 0), array(), '$cmd.sys.inprog');
        return $result['inprog'];
    }
    
    /** @return array */
    public function terminateProcess($processId) {
        return $this->database('admin')->findOne(array('op' => (int)$processId), array(), '$cmd.sys.killop');
    }
    
}
