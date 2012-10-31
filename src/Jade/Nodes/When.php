<?php

namespace Jade\Nodes;

class When extends Node {
    public $expr;
    public $block;

    function __construct($expr, $block) {
        $this->expr = $expr;
        $this->block = $block;
    }
}
