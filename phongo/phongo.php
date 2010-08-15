<?php

/**
 * Compatibility with Nette Framework
 */
require_once dirname(__FILE__) . '/libs/Nette/exceptions.php';

if (!interface_exists('Nette\IDebugPanel', FALSE)) {
    require_once dirname(__FILE__) . '/libs/Nette/IDebugPanel.php';
}

require_once dirname(__FILE__) . '/libs/Exception.php';
require_once dirname(__FILE__) . '/libs/Phongo.php';
require_once dirname(__FILE__) . '/libs/Object.php';
require_once dirname(__FILE__) . '/libs/DateTime.php';
require_once dirname(__FILE__) . '/libs/Reference.php';
require_once dirname(__FILE__) . '/libs/Tools.php';
require_once dirname(__FILE__) . '/libs/Converter.php';
require_once dirname(__FILE__) . '/libs/Json.php';
//require_once dirname(__FILE__) . '/libs/Yaml.php';
require_once dirname(__FILE__) . '/libs/Connection.php';
require_once dirname(__FILE__) . '/libs/Database.php';
require_once dirname(__FILE__) . '/libs/Cursor.php';
require_once dirname(__FILE__) . '/libs/ConnectionInfo.php';
require_once dirname(__FILE__) . '/libs/DatabaseInfo.php';
require_once dirname(__FILE__) . '/libs/Cache.php';
require_once dirname(__FILE__) . '/libs/Profiler.php';

//require_once dirname(__FILE__) . '/libs/Prophet.php';



