<?php

use Jade\Compiler;

class BugCompiler extends Compiler {

    public function __construct() {

        $this->createStatements();
    }
}

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

    /**
     * @expectedException Exception
     */
    public function testMissingClosure() {

        $compiler = new Compiler();
        $compiler->handleCode('["foo"');
    }

    /**
     * @expectedException Exception
     */
    public function testCreateEmptyStatement() {

        new BugCompiler();
    }
}
