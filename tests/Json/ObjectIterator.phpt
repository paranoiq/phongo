<?php

use Phongo\Json\ObjectIterator;

require __DIR__ . '/../initialize.php';


class TestObject implements Iterator {
    public $public = 'public';
    protected $protected = 'protected';
    private $private = 'private';
    private $arr = array(1, 2, 3);
    
    public function current() {
        return current($this->arr);
    }
    public function next() {
        next($this->arr);
    }
    public function key() {
        return key($this->arr);
    }
    public function rewind() {
        reset($this->arr);
    }
    public function valid() {
        return key($this->arr) !== NULL;
    }
}

foreach (new ObjectIterator(new TestObject) as $i => $v) {
    echo "$i:$v;";
}

__halt_compiler() ?>

------EXPECT------
public:public;