<?php

namespace Phongo;

use MongoId;


class Converter {
    
    /**#@+ item format */
    const PHP_ARRAY   = 0;
    const PHONGO      = 1;
    const MONGO       = 2;
    /*const JSON        = 3;
    const YAML        = 4;
    const TENGEN_JSON = 5; // JSON with 10gen objects
    const TENGEN_YAML = 6; // YAML with 10gen objects
    const INLINE_YAML = 7;
    const XML         = 8;
    const CSV         = 9;*/
    /**#@-*/
    
    
    /** Static class - cannot be instantiated. */
    final public function __construct() {
        throw new \LogicException("Cannot instantiate static class " . get_class($this));
    }
    
    
    /// TODO: 10gen!
    public static function jsonToMongo($json) {
        $query = self::fromArray(Json::decode($json), self::MONGO);
        
        return $query;
    }
    
    
    // vyhodit map kvůli Referencím!
    public static function mongoToPhongo($item) {
        $item = Tools::map($item,
            function($item) {
                if ($item instanceof \MongoDate) {
                    return new DateTime($item);
                ///
                } else {
                    return $item;
                }
            });
        return $item;
    }
    
    public static function phongoToMongo($item) {
        $item = Tools::map($item,
            function($item) {
                if ($item instanceof DateTime) {
                    return $item->getMongoDate();
                } elseif ($item instanceof Reference) {
                    return $item->getMongoDBRef();
                } else {
                    return $item;
                }
            });
        return $item;
    }
    
    public static function fromArray($item, $to = self::MONGO) {
        $return = array();
        if (is_array($item) && $item) {
            $keys = array_keys($item);
            switch ((string) $keys[0]) {
            case '$oid':
                return new MongoId($item['$oid']);
                break;
            case '$date':
                $date = new PhongoDate();
                return ($to == self::MONGO) ? $date->getMongoDate() : $date;
                break;
            case '$ref':
                return ($to == self::MONGO) ? $item : new Reference($item['$id'], $item['$ref'], @$item['$db']);
                break;
            case '$binary':
                ///
                throw new \NotImplementedException('$binary is not implemented yet.');
                break;
            case '$regex':
                ///
                throw new \NotImplementedException('$regex is not implemented yet.');
                break;
            default:
                foreach ($item as $key => $val) {
                    $return[$key] = self::fromArray($val, $to);
                }
            }
        } else {
            return $item;
        }
        return $return;
    }
    
    public function toArray($item) {
        
    }
    
}
