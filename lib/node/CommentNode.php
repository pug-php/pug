<?php


class CommentNode extends Node {

    protected $string;

    protected $buffering = false;

    protected $block;

    /**
     * Initialize code node.
     *
     * @param   string  $string     comment string
     * @param   boolean $buffering  turn on buffering
     * @param   integer $line       source line
     */
    public function __construct($string, $buffering = false, $line) {
        parent::__construct($line);

        $this->string       = $string;
        $this->buffering    = $buffering;
    }

    /**
     * Return comment string.
     *
     * @return  string
     */
    public function getString() {
        return $this->string;
    }

    /**
     * Return true if comment buffered.
     *
     * @return  boolean
     */
    public function isBuffered() {
        return $this->buffering;
    }

    /**
     * Set block node.
     *
     * @param   BlockNode   $node   child node
     */
    public function setBlock(BlockNode $node) {
        $this->block = $node;
    }

    /**
     * Return block node.
     *
     * @return  BlockNode
     */
    public function getBlock() {
        return $this->block;
    }
}
