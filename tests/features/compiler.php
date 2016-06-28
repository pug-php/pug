<?php

use Jade\Compiler;

class JadeCompilerTest extends PHPUnit_Framework_TestCase
{
    public function testGoodClosing()
    {
        $compiler = new Compiler();
        $this->assertTrue(is_array($compiler->handleCode('$a = [$b, $e]')));
    }
}
