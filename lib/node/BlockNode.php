<?php

namespace lib\node;

/*
 * This file is part of the Jade.php.
 * (c) 2010 Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Block Node.
 */
class BlockNode extends Node
{
    protected $children = array();

    /**
     * Add child node.
     *
     * @param   Node    $node   child node
     */
    public function addChild(Node $node)
    {
        $this->children[] = $node;
    }

    /**
     * Return child nodes.
     *
     * @return  array           array of Node's
     */
    public function getChildren()
    {
        return $this->children;
    }
}
