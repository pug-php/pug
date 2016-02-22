<?php

use Jade\Compiler;

class StatementsBugCompiler extends Compiler {

    public function __construct() {

        $this->createStatements();
    }
}

class ApplyBugCompiler extends Compiler {

    public function __construct() {

        $this->apply('foo', array());
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
    public function testMissingClosing() {

        $compiler = new Compiler();
        $compiler->handleCode('$a = [$b, c(d$e]');
    }

    /**
     * @expectedException Exception
     */
    public function testCreateEmptyStatement() {

        new StatementsBugCompiler();
    }

    /**
     * @expectedException Exception
     */
    public function testBadMethodApply() {

        new ApplyBugCompiler();
    }
}
