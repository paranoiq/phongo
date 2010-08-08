<?php

namespace Phongo;

use Nette\IDebugPanel;

/**
 * Defines method that must profiler implement.
 */
interface IProfiler {
    /**#@+ event type */
    const CONNECT = 1; // connect
    const SELECT = 4;  // query
    const INSERT = 8;  // insert, batchInsert
    const DELETE = 16; // delete
    const UPDATE = 32; // update, save
    const QUERY = 60;  // 
    const COMMAND = 64; // runCommand
    const EXCEPTION = 512;
    const ALL = 1023;
    /**#@-*/
    
    /**
     * Before event notification.
     * @param  Phongo\Phongo
     * @param  int     event type
     * @param  string  query
     * @return int
     */
    function before(IConnection $connection, $event, $query = NULL);
    
    /**
     * After event notification.
     * @param  int
     * @param  Phongo\Query
     */
    function after($ticket, $result = NULL);
    
    /**
     * After exception notification.
     * @param  Phongo\DatabaseException
     */
    function exception(DatabaseException $exception);
    
}


/**
 * Phongo basic logger & profiler
 * 
 * Profiler options:
 *  - 'filter' - which queries to log?
 */
class Profiler extends Object implements IProfiler, IDebugPanel {
    
    /** maximum number of rows */
    static public $maxQueries = 30;
    
    /** maximum query length */
    static public $maxLength = 1000;
    
    /** @var string  Name of the file where query errors should be logged */
    private $file;
    
    /** @var bool  log to firebug? */
    public $useFirebug;
    
    /** @var bool  explain queries? */
    public $explainQuery = TRUE;
    
    /** @var int */
    private $filter = self::ALL;
    
    /** @var array */
    public static $tickets = array();
    
    /** @var array */
    public static $fireTable = array(array('Time', 'Query', 'Rows', 'Connection'));
    
    /** @var int */
    private static $numOfQueries = 0;
    /** @var int */
    private static $elapsedTime = FALSE;
    /** @var int */
    private static $totalTime = 0;
    
    public function __construct(array $config = array()) {
        if (class_exists('Nette\Debug', FALSE) && is_callable('Nette\Debug::addPanel')) {
            Nette\Debug::addPanel($this);
        }
        
        $this->useFirebug = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'FirePHP/');
        
        if (isset($config['filter'])) {
            $this->setFilter($config['filter']);
        }
        
        if (isset($config['explain'])) {
            $this->explainQuery = (bool) $config['explain'];
        }
    }
    
    
    
    /**
     * @param  string  filename
     * @return Phongo/Profiler  provides a fluent interface
     */
    public function setFile($file) {
        $this->file = $file;
        return $this;
    }
    
    
    
    /**
     * @param  int
     * @return Phongo/Profiler  provides a fluent interface
     */
    public function setFilter($filter) {
        $this->filter = (int) $filter;
        return $this;
    }
    
    
    
    /**
     * Before event notification.
     * @param  Phongo/IConnection
     * @param  int     event name
     * @param  string  query
     * @return int
     */
    public function before(IConnection $connection, $event, $query = NULL) {
        if ($event & self::QUERY) self::numOfQueries++;
        self::$elapsedTime = FALSE;
        self::$tickets[] = array($connection, $event, trim($query), -microtime(TRUE), NULL, NULL);
        end(self::$tickets);
        return key(self::$tickets);
    }
    
    
    
    /**
     * After event notification.
     * @param  int
     * @param  Phongo/ICursor
     * @return void
     */
    public function after($ticket, ICursor $res = NULL) {
        if (!isset(self::$tickets[$ticket])) {
            throw new InvalidArgumentException('Bad ticket number.');
        }
        
        $ticket = & self::$tickets[$ticket];
        $ticket[3] += microtime(TRUE);
        list($connection, $event, $query, $time) = $ticket;
        
        self::$elapsedTime = $time;
        self::$totalTime += $time;
        
        if (($event & $this->filter) === 0) return;
        
        if ($event & self::QUERY) {
            try {
                $ticket[4] = $count = $res instanceof ICursor ? $res->count(TRUE) : '-';
            } catch (Exception $e) {
                $count = '?';
            }
            
            if (count(self::$fireTable) < self::$maxQueries) {
                self::$fireTable[] = array(
                    sprintf('%0.3f', $time * 1000),
                    strlen($query) > self::$maxLength ? substr($query, 0, self::$maxLength) . '...' : $query,
                    $count,
                    'MongoDB' /*. '/' . $connection->getConfig('name')*/
                );
                
                if ($this->explainQuery && $event === self::SELECT) {
                    try {
                        $ticket[5] = $res->explain();
                    } catch (DatabaseException $e) {}
                    $connection->setProfiler($this);
                }
                
                if ($this->useFirebug && !headers_sent()) {
                    header('X-Wf-Protocol-dibi: http://meta.wildfirehq.org/Protocol/JsonStream/0.2');
                    header('X-Wf-dibi-Plugin-1: http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/0.2.0');
                    header('X-Wf-dibi-Structure-1: http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1');
                    
                    $payload = json_encode(array(
                        array(
                            'Type' => 'TABLE',
                            'Label' => 'Phongo profiler (' . self::$numOfQueries . ' queries took ' . sprintf('%0.3f', self::$totalTime * 1000) . ' ms)',
                        ),
                        self::$fireTable,
                    ));
                    foreach (str_split($payload, 4990) as $num => $s) {
                        $num++;
                        header("X-Wf-dibi-1-1-d$num: |$s|\\"); // protocol-, structure-, plugin-, message-index
                    }
                    header("X-Wf-dibi-1-1-d$num: |$s|");
                }
            }

            if ($this->file) {
                $this->writeFile(
                    "OK: " . $query
                    . ($res instanceof ICursor ? ";\n-- rows: " . $count : '')
                    . "\n-- takes: " . sprintf('%0.3f', $time * 1000) . ' ms'
                    . "\n-- driver: " . 'MongoDB'/* . '/' . $connection->getConfig('name')*/
                    . "\n-- " . date('Y-m-d H:i:s')
                    . "\n\n"
                );
            }
        }
    }
    
    
    
    /**
     * After exception notification.
     * @param  Phongo/DatabaseException
     * @return void
     */
    public function exception(DatabaseException $exception) {
        
        if ((self::EXCEPTION & $this->filter) === 0) return;
        
        if ($this->useFirebug) {
            // TODO: implement
        }
        
        if ($this->file) {
            $message = $exception->getMessage();
            $code = $exception->getCode();
            if ($code) {
                $message = "[$code] $message";
            }
            $this->writeFile(
                "ERROR: $message"
                . "\n-- query: " . $exception->getQuery()
                . "\n-- driver: " //. $connection->getConfig('driver')
                . ";\n-- " . date('Y-m-d H:i:s')
                . "\n\n"
            );
        }
    }
    
    
    
    private function writeFile($message) {
        $handle = fopen($this->file, 'a');
        if (!$handle) return; // or throw exception?
        flock($handle, LOCK_EX);
        fwrite($handle, $message);
        fclose($handle);
    }
    
    
    
    /********************* interface Nette\IDebugPanel ****************d*g**/
    
    
    
    /**
     * Returns HTML code for custom tab.
     * @return mixed
     */
    public function getTab() {
        return '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEYSURBVBgZBcHPio5hGAfg6/2+R980k6wmJgsJ5U/ZOAqbSc2GnXOwUg7BESgLUeIQ1GSjLFnMwsKGGg1qxJRmPM97/1zXFAAAAEADdlfZzr26miup2svnelq7d2aYgt3rebl585wN6+K3I1/9fJe7O/uIePP2SypJkiRJ0vMhr55FLCA3zgIAOK9uQ4MS361ZOSX+OrTvkgINSjS/HIvhjxNNFGgQsbSmabohKDNoUGLohsls6BaiQIMSs2FYmnXdUsygQYmumy3Nhi6igwalDEOJEjPKP7CA2aFNK8Bkyy3fdNCg7r9/fW3jgpVJbDmy5+PB2IYp4MXFelQ7izPrhkPHB+P5/PjhD5gCgCenx+VR/dODEwD+A3T7nqbxwf1HAAAAAElFTkSuQmCC">'
            . self::$numOfQueries . ' queries';
    }
    
    
    
    /**
     * Returns HTML code for custom panel.
     * @return mixed
     */
    public function getPanel() {
        if (!self::$numOfQueries) return;
        
        $content = "
<h1>Queries: " . self::$numOfQueries . (self::$totalTime === NULL ? '' : ', time: ' . sprintf('%0.3f', self::$totalTime * 1000) . ' ms') . "</h1>

<style>
    #nette-debug-DibiProfiler td.dibi-sql { background: white }
    #nette-debug-DibiProfiler .nette-alt td.dibi-sql { background: #F5F5F5 }
    #nette-debug-DibiProfiler .dibi-sql div { display: none; margin-top: 10px; max-height: 150px; overflow:auto }
</style>

<div class='nette-inner'>
<table>
<tr>
    <th>Time</th><th>Query</th><th>Rows</th><th>Connection</th>
</tr>
";
        $i = 0; $classes = array('class="nette-alt"', '');
        foreach (self::$tickets as $ticket) {
            list($connection, $event, $query, $time, $count, $explain) = $ticket;
            if (!($event & self::QUERY)) continue;
            $content .= "
<tr {$classes[++$i%2]}>
    <td>" . sprintf('%0.3f', $time * 1000) . ($explain ? "
    <br><a href='#' class='nette-toggler' rel='#nette-debug-DibiProfiler-row-$i'>explain&nbsp;&#x25ba;</a>" : '') . "</td>
    <td class='dibi-sql'>" . self::dump((strlen($query) > self::$maxLength) ? substr($query, 0, self::$maxLength) . '...' : $query, TRUE) . ($explain ? "
    <div id='nette-debug-DibiProfiler-row-$i'>{$explain}</div>" : '') . "</td>
    <td>{$count}</td>
    <td>" . htmlSpecialChars($connection->getConfig('driver') . '/' . $connection->getConfig('name')) . "</td>
</tr>
";
        }
        $content .= '</table></div>';
        return $content;
    }
    
    
    
    /**
     * Returns panel ID.
     * @return string
     */
    public function getId() {
        return get_class($this);
    }
    
}
