<?php

namespace lib\node;

class DoctypeNode extends Node {

    protected $version;

    /**
     * Initialize doctype node.
     *
     * @param   string  $version    doctype version
     * @param   integer $line       source line
     */
    public function __construct($version, $line) {
        parent::__construct($line);

        $this->version = $version;
    }

    /**
     * Return doctype version.
     *
     * @return  string
     */
    public function getVersion() {
        return $this->version;
    }
}
