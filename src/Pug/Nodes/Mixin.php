<?php

namespace Pug\Nodes;

class Mixin extends Attributes
{
    public $name;
    public $arguments;
    public $block;
    public $attributes;
    public $call;

    public function __construct($name, $arguments, $block, $call)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->block = $block;
        $this->attributes = [];
        $this->call = $call;
    }
}
