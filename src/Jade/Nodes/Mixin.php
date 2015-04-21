<?php

namespace Jade\Nodes;

use Jade\Compiler;

class Mixin extends Attributes {
    public $name;
    public $arguments;
    public $block;
    public $attributes;
    public $call;

    public function __construct($name, $arguments, $block, $call) {

        $this->name = $name;
        $this->arguments = (preg_match('/^' . Compiler::VARNAME . '$/', $arguments) ? '$' : '') . $arguments;
        $this->block = $block;
        $this->attributes = array();
        $this->call = $call;
    }
}
