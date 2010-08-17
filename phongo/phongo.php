<?php

/**
 * Compatibility with Nette Framework
 */
require_once dirname(__FILE__) . '/libs/Nette/exceptions.php';

if (!interface_exists('Nette\IDebugPanel', FALSE)) {
    require_once dirname(__FILE__) . '/libs/Nette/IDebugPanel.php';
}
if (!class_exists('Nette\Json', FALSE)) {
    require_once dirname(__FILE__) . '/libs/Nette/Json.php';
}


require_once dirname(__FILE__) . '/libs/exceptions.php';
require_once dirname(__FILE__) . '/libs/Phongo.php';
require_once dirname(__FILE__) . '/libs/Object.php';

require_once dirname(__FILE__) . '/libs/types/DateTime.php';
require_once dirname(__FILE__) . '/libs/types/ObjectId.php';
require_once dirname(__FILE__) . '/libs/types/Reference.php';

require_once dirname(__FILE__) . '/libs/Tools.php';
require_once dirname(__FILE__) . '/libs/Converter.php';
require_once dirname(__FILE__) . '/libs/Connection.php';
require_once dirname(__FILE__) . '/libs/Database.php';
require_once dirname(__FILE__) . '/libs/Cursor.php';
require_once dirname(__FILE__) . '/libs/ConnectionInfo.php';
require_once dirname(__FILE__) . '/libs/DatabaseInfo.php';
require_once dirname(__FILE__) . '/libs/Cache.php';
require_once dirname(__FILE__) . '/libs/Profiler.php';

require_once dirname(__FILE__) . '/libs/Json/Serialiser.php';
require_once dirname(__FILE__) . '/libs/Json/Formater.php';

function bar($value) {
    Nette\Debug::barDump($value);
}

