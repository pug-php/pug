<?php

namespace Jade\Nodes;

class Attributes extends Node {
    public $attributes = array();

    public function setAttribute($name, $value, $escaped=false) {
        array_push($this->attributes, array('name'=>$name,'value'=>$value,'escaped'=>$escaped));
        return $this;
    }

    public function removeAttribute($name) {
        foreach ($this->attributes as $k => $attr) {
            if ($attr['name'] == $name) {
                unset($this->attributes[$k]);
            }
        }
    }

    public function getAttribute($name) {
        foreach ($this->attributes as $attr) {
            if ($attr['name'] == $name) {
                return $attr;
            }
        }
    }
}
