<?php

use Jade\Compiler;

class JadeCompilerExceptionsTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException Exception
     */
    public function testHandleEmptyCode() {

        $compiler = new Compiler();
        $compiler->handleCode('');
    }

    /**
     * @expectedException Exception
     */
    public function testNonStringInHandleCode() {

        $compiler = new Compiler();
        $compiler->handleCode(array());
    }
}
