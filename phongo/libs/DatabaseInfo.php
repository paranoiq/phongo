<?php

namespace Phongo;


class DatabaseInfo extends Object {
    
    /** @var IPhongo */
    private $db;
    
    public function __construct(IConnection $connection) {
        $this->db = $connection;
    }
    
    
    
    /** @return string */
    public function getServerInfo() {
        return $this->db->runCommand(array('buildInfo' => 1), 'admin');
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
        $options = $this->db->runCommand(array('getCmdLineOpts' => 1), 'admin');
        return $options['argv'];
    }
    
    /** @return array */
    public function getServerStatus() {
        /// fuj
        $status = Converter::mongoToPhongo($this->db->runCommand(array('serverStatus' => 1), 'admin'));
        unset($status['ok']);
        return $status;
    }
    
    /** @param string */
    public function getCommandList() {
        $stats = $this->db->runCommand(array('listCommands' => 1), 'admin');
        return $stats['commands'];
    }
    
    /** @param string */
    public function getDatabaseStats($database = NULL) {
        $stats = $this->db->runCommand(array('dbStats' => 1), $database);
        unset($stats['ok']);
        return $stats;
    }
    
    /** @param string */
    public function getCollectionStats($collection, $database = NULL) {
        $stats = $this->db->runCommand(array('collStats' => $collection), $database);
        unset($stats['ok']);
        return $stats;
    }
    
    /** @return array */
    public function getCollectionsUsage() {
        $usage = $this->db->runCommand(array('top' => 1), 'admin');
        return $usage['totals'];
    }
    
}
