<?php

use Pug\Compiler;
use Pug\Pug;

class ExpressionCompilerTester extends Compiler
{
    public function callPhpizeExpression()
    {
        return call_user_func_array(array($this, 'phpizeExpression'), func_get_args());
    }
}

class PugExpressionLanguageTest extends PHPUnit_Framework_TestCase
{
    public function testJsExpression()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'expressionLanguage' => 'js',
        ));

        $actual = trim($Pug->render("- a = 2\n- b = 4\n- c = b * a\np=a * b\np=c"));
        $this->assertSame('<p>8</p><p>8</p>', $actual);

        $actual = trim($Pug->render("- a = 2\n- b = 4\np=a + b"));
        $this->assertSame('<p>6</p>', $actual);

        $actual = trim($Pug->render("- a = '2'\n- b = 4\np=a + b"));
        $this->assertSame('<p>24</p>', $actual);

        $compiler = new ExpressionCompilerTester(array(
            'expressionLanguage' => 'js',
        ));
        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'array(1)'));
        $this->assertSame('array(1)', $actual);
        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('$a', $actual);

        $actual = trim($Pug->render("mixin test\n  div&attributes(attributes)\nbody\n  +test()(class='test')"));
        $this->assertSame('<body><div class="test "></div></body>', $actual);
    }

    public function testPhpExpression()
    {
        $compiler = new ExpressionCompilerTester(array(
            'expressionLanguage' => 'php',
        ));

        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('a', $actual);
    }

    public function testAutoExpression()
    {
        $compiler = new ExpressionCompilerTester(array(
            'expressionLanguage' => 3.123,
        ));

        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('$a', $actual);
    }

    public function testJsLanguageOptions()
    {
        $Pug = new Pug(array(
            'expressionLanguage' => 'js',
            'jsLanguage' => array(
                'helpers' => array(
                    'dot' => 'plus',
                ),
            ),
        ));

        $actual = trim($Pug->render('=a.ho', array(
            'a' => 'hi '
        )));
        $this->assertSame('hi ho', $actual);
    }
}
