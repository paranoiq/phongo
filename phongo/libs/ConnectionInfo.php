<?php

namespace Phongo;


class ConnectionInfo extends Object {
    
    /** @var IConnection */
    private $conn;
    
    
    public function __construct(IConnection $connection) {
        $this->conn = $connection;
    }
    
    
    /** @return string */
    public function getServerInfo() {
        return $this->conn->admin->runCommand(array('buildInfo' => 1));
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
        $options = $this->conn->admin->runCommand(array('getCmdLineOpts' => 1));
        return $options['argv'];
    }
    
    /** @return array */
    public function getServerStatus() {
        $status = Converter::mongoToPhongo($this->conn->admin->runCommand(array('serverStatus' => 1)));
        unset($status['ok']);
        return $status;
    }
    
    /** @return array */
    public function getCommandList() {
        $list = $this->conn->admin->runCommand(array('listCommands' => 1));
        return $list['commands'];
    }
    
    /** @return array */
    private function getDatabaseInfo() {
        $info = $this->conn->admin->runCommand(array('listDatabases' => 1));
        unset($info['ok']);
        return $info;
    }
    
    /** @return array */
    public function getDatabaseList() {
        $res = $this->conn->getCache()->get('databases', Cache::RUNTIME);
        if ($res) return $res;
        
        $result = $this->getDatabaseInfo();
        $list = array();
        foreach ($result['databases'] as $database) {
            $list[$database['name']] = $database['name'];
        }
        
        $this->conn->getCache()->set('databases', $list, Cache::RUNTIME);
        return $list;
    }
    
    /** @return array */
    public function getUsage() {
        $usage = $this->conn->getCache()->get('usage', Cache::RUNTIME);
        if (!$usage) {
            $usage = $this->conn->admin->runCommand(array('top' => 1));
            $this->conn->getCache()->set('usage', $usage, Cache::RUNTIME);
        }
        
        return $usage['totals'];
    }
    
}
