<?php


class BlockNode extends Node {

    protected $children = array();

    /**
     * Add child node.
     *
     * @param   Node    $node   child node
     */
    public function addChild(Node $node) {
        $this->children[] = $node;
    }

    /**
     * Return child nodes.
     *
     * @return  array           array of Node's
     */
    public function getChildren() {
        return $this->children;
    }
}
