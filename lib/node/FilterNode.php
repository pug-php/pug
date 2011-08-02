<?php


class FilterNode extends Node {

    protected $name;

    protected $attributes = array();

    protected $block;

    /**
     * Initialize Filter node.
     *
     * @param   string  $name       filter name
     * @param   array   $attributes filter attributes
     * @param   integer $line       source line
     */
    public function __construct($name, array $attributes = array(), $line) {
        parent::__construct($line);

        $this->name         = $name;
        $this->attributes   = $attributes;
    }

    /**
     * Set block node to filter.
     *
     * @param   BlockNode|TextNode  $node   filtering node
     */
    public function setBlock(Node $node) {
        $this->block = $node;
    }

    /**
     * Return block node to filter.
     *
     * @return  BlockNode|TextNode
     */
    public function getBlock() {
        return $this->block;
    }

    /**
     * Return filter name.
     *
     * @return  string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Return attributes array
     *
     * @return  array               associative array of attributes
     */
    public function getAttributes() {
        return $this->attributes;
    }
}
