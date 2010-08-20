<?php

namespace Phongo;

use MongoDate;


class DateTime extends \DateTime {
    
    /** @var int */
    public $usec;
    
    
    /**
     * @param MongoDate|string
     * @param DateTimeZone $timezone = NULL     
     */
    public function __construct($time = 'now', DateTimeZone $timezone = NULL) {
        $usec = 0;
        if ($time instanceof MongoDate) {
            parent::__construct();
            $this->setTimestamp($time->sec);
            $this->usec = $time->usec;
        } elseif ($timezone) {
            parent::__construct($time, $timezone);
        } else {
            parent::__construct($time);
        }
    }
    
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->format('Y-m-d H:i:s');
    }
    
    
    /**
     * @return MongoDate
     */
    public function getMongoDate() {
        return new MongoDate($this->format('U'), $this->usec);
    }
    
}
