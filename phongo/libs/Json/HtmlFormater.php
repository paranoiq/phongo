<?php

namespace Phongo\Json;


/**
 * HTML formater
 */
class HtmlFormater extends ExtendedFormater {
    
    
    /** @var int */
    protected $path = array();
    
    /** @var bool */
    protected $liveUrls = FALSE;
    
    
    public function __construct($options = 0) {
        parent::__construct($options);
        $this->liveUrls = !empty($options['liveUrls']);
        ///
    }
    
    protected function getPath() {
        ///
    }
    
    
    protected function wrap($type, $value) {
        return "<span class='t-$type'>$value</span>";
    }
    
    protected function wrapStr($value) {
        return "<span class='t-string'>\"$value\"</span>";
    }
    
    protected function wrapKey($value) {
        return "<span class='t-key'>" . ($this->quoteKeys ? '"' : '') . $value . ($this->quoteKeys ? '"' : '') . "</span>";
    }
    
    
    public function formatBool($value) {
        return $this->wrap('bool', parent::formatBool($value));
    }
    
    public function formatInt($value) {
        return $this->wrap('int', parent::formatInt($value));
    }
    
    public function formatFloat($value) {
        return $this->wrap('float', parent::formatFloat($value));
    }
    
    public function formatString($value) {
        static $regex = '#((?:(?:ht|f)tps?)\://(?:[a-zA-Z0-9\.\-]+(?:\:[-a-zA-Z0-9.&%$]+)*@)*(?:(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9])\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[0-9])|localhost|(?:[-a-zA-Z0-9]+\.)*[-a-zA-Z0-9]+\.(?:[a-z]{2,6}))(?:\:[0-9]+)*(?:/(?:[-a-zA-Z0-9.,?\'\\+&%$\\#=~_]+))*/?)(;?)#';
        
        $str = parent::formatString(htmlspecialchars($value));
        if ($this->liveUrls) {
            $str = preg_replace_callback($regex, array($this, 'link_cb'), $str);
        }
        return $this->wrap('string', $str);
    }
    
    public function link_cb($url) {
        $url = $url[1];
        $q = '';
        if (substr($url, -5) == '&quot' && !empty($url[2])) {
            $url = substr($url, 0, -5);
            $q = '&quot;';
        }
        return "<a href='$url' rel='nofollow'>$url</a>" . $q;
    }
    
    public function formatObjectId($id) {
        if ($this->tenGen) {
            $val = 'ObjectId(' . $this->wrapStr($id) . ')';
        } else {
            $val = '{ ' . $this->wrapKey('$oid') . ': ' . $this->wrapStr($id) . ' }';
        }
        return $this->wrap('id', $val);
    }
    
    public function formatReference($id, $collection, $database = NULL) {
        if ($this->tenGen) {
            $val = 'Dbref(' . $this->wrapStr($collection) . ', ' . $this->wrapStr($id) . (isset($database) ? ', ' . $this->wrapStr($database) : '') . ')';
        } else {
            $val = '{ ' . $this->wrapKey('$ref') . ': ' . $this->wrapStr($collection) . ', ' . $this->wrapKey('$id') . ': ' . $this->wrapStr($id) . (isset($database) ? ', ' . $this->wrapKey('$db') . ': ' . $this->wrapStr($database) : '') . ' }';
        }
        return $this->wrap('ref', $val);
    }
    
    public function formatDate($value) {
        if ($this->tenGen) {
            $val = 'Date(' . $this->wrapStr($value) . ')';
        } else {
            $val = '{ ' . $this->wrapKey('$date') . ': ' . $this->wrapStr($value) . ' }';
        }
        return $this->wrap('date', $val);
    }
    
    public function formatRegex($regex, $params) {
        if ($this->tenGen) {
            $val = '/' . $regex . '/' . $params;
        } else {
            $val = '{ ' . $this->wrapKey('$regex') . ': ' . $this->wrapStr($regex) . ', ' . $this->wrapKey('$options') . ': ' . $this->wrapStr($params) . ' }';
        }
        return $this->wrap('regex', $val);
    }
    
    public function formatBinData($data, $type) {
        return $this->wrap('bin', '{ ' . $this->wrapKey('$binary') . ': ' . $this->wrapStr(base64_encode($data)) . ', ' . $this->wrapKey('$type') . ': ' . $this->wrapStr($type) . ' }');
    }
    
    public function formatMinKey() {
        return ($this->quoteKeys)
            ? $this->wrap('min', '{ "$minKey": 1 }')
            : $this->wrap('min', '{ $minKey: 1 }');
    }
    
    public function formatMaxKey() {
        return ($this->quoteKeys)
            ? $this->wrap('max', '{ "$maxKey": 1 }')
            : $this->wrap('max', '{ $maxKey: 1 }');
    }
    
    public function formatCode($code) {
        /// pravděpodobně binary type MongoBinData::FUNC
        throw new Exception('NYI');
    }
    
    public function beginArray() {
        $ret = '';
        if ($this->prev == self::OBJECT_CONTEXT) $ret = "\n" . $this->indent();
        $this->context[] = self::ARRAY_CONTEXT;
        $this->level++;
        return $ret . "<span class='t-array'><span class='t-par'>[</span>";
    }
    
    public function endArray() {
        array_pop($this->context);
        $this->level--;
        $ret = '';
        if ($this->prev == self::OBJECT_CONTEXT) $ret = "\n" . $this->indent();
        $this->prev = self::ARRAY_CONTEXT;
        return $ret . "<span class='t-par'>]</span></span>";
    }
    
    public function beginObject() {
        $ret = '';
        if ($this->context[$this->level] == self::ARRAY_CONTEXT) $ret = "\n" . $this->indent(); 
        $this->context[] = self::OBJECT_CONTEXT;
        $this->level++;
        return $ret . "<span class='t-object'><span class='t-par'>{</span>";
    }
    
    public function endObject() {
        array_pop($this->context);
        $this->level--;
        $this->prev = self::OBJECT_CONTEXT;
        return "\n" . $this->indent() . "<span class='t-par'>}</span></span>";
    }
    
    public function beginPair() {
        $this->prev = self::NO_CONTEXT;
        return "\n" . $this->indent() . "<span class='t-pair'>";
    }
    
    public function endPair() {
        return '</span>';
    }
    
    public function formatKey($key) {
        return $this->wrapKey(trim(parent::formatString(htmlspecialchars((string) $key)), '"')) . ': ';
    }
    
    public function nextItem() {
        return ($this->context[$this->level] == self::ARRAY_CONTEXT) ? ', ' : ',';
    }
    
}
