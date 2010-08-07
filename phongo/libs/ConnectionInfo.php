<?php

namespace Phongo;


class ConnectionInfo extends Object {
    
    /** @var IConnection */
    private $db;
    
    /** @var array metadata cache */
    private $cache = array();
    /** @var bool */
    private $useCache = FALSE;
    
    
    public function __construct(IConnection $connection) {
        $this->db = $connection;
    }
    
    
    /**
     * @param bool
     * @return ConnectionInfo
     */
    public function useCache($useCache = TRUE) {
        $this->useCache = (bool) $useCache;
        return $this;
    }
    
    
    /** @return string */
    public function getServerInfo() {
        return $this->db->admin->runCommand(array('buildInfo' => 1));
    }
    
    /** @return string */
    public function getVersionInfo() {
        $info = $this->getServerInfo();
        list($system, $a, $b, $c) = explode(' ', $info['sysInfo']);
        if (strtolower($system) == 'linux')   $system .= " $b";
        if (strtolower($system) == 'windows') $system .= str_replace(array('(', ','), '', " $a.$b.$c");
        /// TODO: others
        return "<b>$info[version]</b>, $info[bits]bit on " . ucfirst($system);
    }
    
    /** @return array */
    public function getStartupOptions() {
        $options = $this->db->admin->runCommand(array('getCmdLineOpts' => 1));
        return $options['argv'];
    }
    
    /** @return array */
    public function getServerStatus() {
        /// fuj
        $status = Converter::mongoToPhongo($this->db->admin->runCommand(array('serverStatus' => 1)));
        unset($status['ok']);
        return $status;
    }
    
    /** @return array */
    public function getCommandList() {
        if ($this->useCache && !empty($this->cache['commands'])) return $this->cache['commands'];
        
        $stats = $this->db->admin->runCommand(array('listCommands' => 1));
        
        $this->cache['commands'] = $stats['commands'];
        return $stats['commands'];
    }
    
    /** @return array */
    private function getDatabaseInfo() {
        $info = $this->db->admin->runCommand(array('listDatabases' => 1));
        unset($info['ok']);
        return $info;
    }
    
    /** @return array */
    public function getDatabaseList() {
        if ($this->useCache && !empty($this->cache['databases'])) return $this->cache['databases'];
        
        $result = $this->getDatabaseInfo();
        $list = array();
        foreach ($result['databases'] as $database) {
            $list[$database['name']] = $database['name'];
        }
        
        $this->cache['databases'] = $list;
        return $list;
    }
    
    /** @return array */
    public function getCollectionsUsage() {
        $usage = $this->db->admin->runCommand(array('top' => 1));
        return $usage['totals'];
    }
    
}