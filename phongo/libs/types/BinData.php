<?php

namespace Phongo;

use MongoBinData;


/**
 * MongoDB binary data wrapper
 * 
 * @property-read data
 */
class BinData extends Object {
    
    /** @var string */
    private $data;
    
    
    /** @param string|MongoBinData */
    public function __construct($data) {
        if (is_string($data)) {
            $this->data = $data;
        } elseif ($data instanceof MongoBinData) {
            if (!$data->type === MongoBinData::BYTE_ARRAY)
                throw new \InvalidArgumentException('Unsupported binary data type.');
            $this->data = $data->bin;
        } else {
            throw new \InvalidArgumentException('BinData receives only a string or MongoBinData.');
        }
    }
    
    /** @return MongoBinData */
    public function getMongoBinData() {
        return new MongoBinData($this->data, MongoBinData::BYTE_ARRAY);
    }
    
    /** @return string */
    public function getData() {
        return $this->data;
    }
    
    /** @return string */
    public function __toString() {
        return 'base64,' . base64_encode($this->data);
    }
    
}
