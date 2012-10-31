<?php

namespace Jade\Nodes;

class BlockComment extends Node {
    public $block;
    public $value;
    public $buffer;

    public function __construct($value, $block, $buffer) {
        $this->block = $block;
        $this->value = $value;
        $this->buffer = $buffer;
    }
}
