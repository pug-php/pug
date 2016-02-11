<?php

use Jade\Nodes\Block;
use Jade\Nodes\Tag;

class BlockTest extends PHPUnit_Framework_TestCase {

    /**
     * Attributes Node test
     */
    public function testAttributes() {

        $foo = new Block();
        $bar = new Block(new Tag('small'));
        $this->assertTrue($foo->isEmpty(), 'The block should be empty');
        $bar->replace($foo);
        $this->assertFalse($foo->isEmpty(), 'The block should not be empty');
        $this->assertTrue($foo->nodes[0]->isInline(), 'small tag should be inline');
        $this->assertTrue($foo->nodes[0]->canInline(), 'small tag should can be inline as it does not have children');
    }
}
