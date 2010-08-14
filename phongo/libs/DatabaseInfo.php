<?php

namespace Phongo;


class DatabaseInfo extends Object {
    
    /** @var IDatabase */
    private $db;
    
    
    public function __construct(IDatabase $database) {
        $this->db = $database;
    }
    
    
    /** @return array */
    public function getDatabaseStats() {
        $stats = $this->db->runCommand(array('dbStats' => 1));
        unset($stats['ok']);
        return $stats;
    }
    
    /** @return array<array> */
    private function getNamespaces() {
        $res = $this->db->getCache()->get('namespaces', Cache::RUNTIME);
        if ($res) return $res;
        
        $list = $this->db->find(array(), array(), 'system.namespaces')->order(array('name' => 1))->fetchAll();
        
        $this->db->getCache()->set('namespaces', $list, Cache::RUNTIME);
        return $list;
    }
    
    /** @return array */
    public function getCollectionList() {
        $result = $this->getNamespaces();
        $list = array();
        foreach ($result as $ns) {
            if (preg_match('/[$]|^[^.]+\.(system\.|local\.)/', $ns['name'])) continue;
            $collection = substr($ns['name'], strpos($ns['name'], '.') + 1);
            $list[$collection] = $collection;
        }
        return $list;
    }
    
    /**
     * @param string
     * @return array<array>|array
     */
    public function getCollectionInfo($collection = NULL) {
        $result = $this->getNamespaces();
        $list = array();
        foreach ($result as $ns) {
            if (preg_match('/[$]|^[^.]+\.(system\.|local\.)/', $ns['name'])) continue;
            $coll = substr($ns['name'], strpos($ns['name'], '.') + 1);
            unset($ns['options']['create']);
            $list[$coll] = isset($ns['options']) ? $ns['options'] : array();
        }
        
        if ($collection === NULL) return $list;
        if (!isset($list[$collection])) return NULL;
            //throw new \InvalidArgumentException("Collection '$collection' does not exist.");
        return $list[$collection];
    }
    
    /**
     * @param string
     * @return array
     */
    public function getIndexList($collection = NULL) {
        $namespace = $this->db->getName() . '.' . ($collection ?: $this->db->getCollectionName());
        $cursor = $this->db->find(array('ns' => $namespace), array(), 'system.indexes')->order(array('name' => 1));
        $indexes = array();
        while ($index = $cursor->fetch()) {
            $keys = implode(',', array_keys($index['key']));
            $indexes[$index['name']] = $keys;
        }
        return $indexes;
    }
    
    /**
     * @param string
     * @return array
     */
    public function getCollectionStats($collection = NULL) {
        if (!$collection) $collection = $this->db->getCollectionName();
        $stats = $this->db->runCommand(array('collStats' => $collection));
        unset($stats['ok']);
        return $stats;
    }
    
    /**
     * @param string    
     * @return array
     */
    public function getUsage($collection = NULL) {
        $usage = $this->db->getConnection()->getInfo()->getUsage();
        if (!isset($collection)) return $usage[$this->db->getName()];
        //dump($usage);
        //exit;
        return $usage[$this->db->getName() . '.' . $collection];
    }
    
}
