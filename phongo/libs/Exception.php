<?php

namespace Phongo;

use Nette\IDebugPanel;


/**
 * Any Database error
 */
class DatabaseException extends \Exception /*implements IDebugPanel*/ {
    
    public $result;
    
    public function __construct($message, $result = NULL) {
        parent::__construct($message);
        $this->result = $result;
    }
    
}
