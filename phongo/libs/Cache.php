<?php

/**
 * Phongo cache manager and cache storage drivers
 * 
 * Some parts taken from library NotORM written by Jakub Vrána (http://github.com/vrana/notorm)
 */

namespace Phongo;


/**
 * Cache manager
 */
class Cache extends Object implements \ArrayAccess {
    
    /**#@+ Cache item lifetime (of course, 'longtime' does not mean 'permanent') */
    const RUNTIME = NULL;
    const SESSION = FALSE;
    const LONGTIME = TRUE;
    /**#@-*/
    
    
    /** @var array */
    private $runtime = array();
    
    /** @var Phongo\Storage chache storage */
    private $cache;
    
    
    /**
     * @param Phongo\ICacheStorage permanent cache storage. default is NullCache!
     */
    public function __construct(ICacheStorage $cache = NULL) {
        if (!$cache) $cache = new NullCache();
        $this->cache = $cache;
    }
    
    
    /**
     * Retrieve value from cache
     * @param string
     * @return mixed
     */
    public function get($key) {
        if (isset($this->runtime[$key])) return $this->runtime[$key];
        if (isset($_SESSION['PhongoCache'][$key])) return $_SESSION['PhongoCache'][$key];
        return $this->cache->get($key);
    }
    
    /**
     * Save value into cache
     * @param string
     * @param mixed
     * @param bool|int flag or seconds
     */
    public function set($key, $value, $lifetime = Cache::RUNTIME) {
        if ($lifetime === Cache::RUNTIME) {
            $this->runtime[$key] = $value;
        } elseif ($lifetime === Cache::SESSION) {
            $_SESSION['PhongoCache'][$key] = $value;
        } elseif ($lifetime === Cache::LONGTIME) {
            $this->cache->set($key, $value);
        } else {
            $this->cache->set($key, $value, (int) $lifetime);
        }
    }
    
    /**
     * Remove value from cache
     * @param string
     */
    public function remove($key) {
        unset($this->runtime[$key]);
        unset($_SESSION['PhongoCache'][$key]);
        $this->cache->remove($key);
    }
    
    
    public function offsetGet($key) {
        $val = $this->get($key);
        return $val;
    }
    
    public function offsetSet($key, $value) {
        $this->set($key, $value);
    }
    
    public function offsetUnset($key) {
        $this->unset($key);
    }
    
    public function offsetExists($key) {
        return $this->get($key) !== NULL;
    }
    
}


/**
 * Cache storage interface
 */
interface ICacheStorage {
    public function get($key);
    public function set($key, $value, $lifetime = 0);
    public function remove($key);
}


/**
 * NullCache stores nothing and always returns NULL
 */
class NullCache extends Object implements ICacheStorage {
    
    public function get($key) {
        return NULL;
    }
    
    public function set($key, $value, $lifetime = 0) {
        // OK
    }
    
    public function remove($key) {
        // OK
    }
    
}


/**
 * MongoDB storage
 * 
 * Uses collection `PhongoCache`. This should be a capped collection with unique index on key `id`
 */
class MongoCache extends Object implements ICacheStorage {
    
    private $db;
    
    public function __construct(Database $db) {
        $db->setStrictMode(FALSE);
        $this->db = $db;
    }
    
    public function get($key) {
        $result = $this->findOne(array('id' => $key), array(), 'PhongoCache');
        if (!$result) return NULL;
        
        if (isset($result['expires']) && $result['expires'] < time()) {
            $this->remove($key);
            return NULL;
        }
        
        return unserialize($result['data']);
    }
    
    public function set($key, $value, $lifetime = 0) {
        $data = array('id' => $key, 'data' => serialize($value));
        if ($lifetime) $data['expires'] = time() + $lifetime;
        
        $this->db->save($data, 'PhongoCache');
    }
    
    public function remove($key) {
        $this->db->delete(array('id' => $key), 'PhongoCache');
    }
    
}


/**
 * Memcache storage
 */
class MemcacheCache extends Object implements ICacheStorage {
    
    private $memcache;
    
    public function __construct(\Memcache $memcache) {
        $this->memcache = $memcache;
    }
    
    public function get($key) {
        $value = $this->memcache->get("PhongoCache.$key");
        if ($value === false) return NULL;
        
        return $value;
    }
    
    public function set($key, $value, $lifetime = 0) {
        $this->memcache->set("PhongoCache.$key", $value, FALSE, $lifetime);
    }
    
    public function remove($key) {
        $this->memcache->delete("PhongoCache.$key");
    }
    
}


/**
 * APC storage
 */
class ApcCache extends Object implements ICacheStorage {
    
    public function get($key) {
        $value = apc_fetch("PhongoCache.$key", $ok);
        if (!$ok) return NULL;
        
        return $value;
    }
    
    public function set($key, $value, $lifetime = 0) {
        apc_store("PhongoCache.$key", $data, $lifetime);
    }
    
    public function remove($key) {
        apc_delete("PhongoCache.$key");
    }
    
}


/**
 * PDO SQL database storage
 * 
 * Uses table `phongo_cache` with columns `id` (string), `data` (text) and `expires` (integer).
 * Table has to be created before using cache!
 */
class PdoCache extends Object implements ICacheStorage {
    
    private $connection;
    
    public function __construct(\PDO $connection) {
        $this->connection = $connection;
    }
    
    public function get($key) {
        $result = $this->connection->prepare("SELECT data, expires FROM phongo_cache WHERE id = ?");
        $result->execute(array($key));
        $row = $result->fetch(PDO::FETCH_NUM);
        if (!$row) return NULL;
        
        // cache expired
        if (!empty($row[1]) && $row[1] < time()) {
            $this->remove($key);
            return NULL;
        }
        
        return unserialize($row[0]);
    }
    
    public function set($key, $value, $lifetime = 0) {
        // use PHP time. CURRENT_TIMESTAMP etc. won't work on all DB systems
        $expires = time() + $lifetime;
        $parameters = array(serialize($value), $expires, $key);
        
        // REPLACE is not supported by PostgreSQL and MS SQL
        $result = $this->connection->prepare("UPDATE phongo_cache SET data = ?, expires = ? WHERE id = ?");
        $result->execute($parameters);
        if (!$result->rowCount()) {
            $result = $this->connection->prepare("INSERT INTO phongo_cache (data, expires, id) VALUES (?, ?, ?)");
            try {
                @$result->execute($parameters); // @ - ignore duplicate key error
            } catch (PDOException $e) {
                if ($e->getCode() != "23000") { // "23000" - duplicate key
                    throw $e;
                }
            }
        }
    }
    
    public function remove($key) {
        $result = $this->connection->prepare("DELETE FROM phongo_cache WHERE id = ?");
        $result->execute(array($key));
    }
    
}
