<?php

namespace lib\node;

abstract class Node {

    protected $line;

    /**
     * Initialize node.
     *
     * @param   integer $line   source line
     */
    public function __construct($line) {
        $this->line = $line;
    }

    /**
     * Return node source line.
     *
     * @return  integer
     */
    public function getLine() {
        return $this->line;
    }
}
