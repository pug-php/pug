<?php

use Pug\Compiler;
use Pug\Pug;

class ExpressionCompilerTester extends Compiler
{
    public function callPhpizeExpression()
    {
        return call_user_func_array([$this, 'phpizeExpression'], func_get_args());
    }
}

class PugExpressionLanguageTest extends PHPUnit_Framework_TestCase
{
    public function testJsExpression()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'expressionLanguage' => 'js',
        ]);

        $actual = trim($pug->render("- a = 2\n- b = 4\n- c = b * a\np=a * b\np=c"));
        $this->assertSame('<p>8</p><p>8</p>', $actual);

        $actual = trim($pug->render("- a = 2\n- b = 4\np=a + b"));
        $this->assertSame('<p>6</p>', $actual);

        $actual = trim($pug->render("- a = '2'\n- b = 4\np=a + b"));
        $this->assertSame('<p>24</p>', $actual);

        $compiler = new ExpressionCompilerTester([
            'expressionLanguage' => 'js',
        ]);
        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'array(1)'));
        $this->assertSame('array(1)', $actual);
        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('$a', $actual);

        $actual = trim($pug->render("mixin test\n  div&attributes(attributes)\nbody\n  +test()(class='test')"));
        $this->assertSame('<body><div class="test "></div></body>', $actual);
    }

    public function testPhpExpression()
    {
        $compiler = new ExpressionCompilerTester([
            'expressionLanguage' => 'php',
        ]);

        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('a', $actual);
    }

    public function testAutoExpression()
    {
        $compiler = new ExpressionCompilerTester([
            'expressionLanguage' => 3.123,
        ]);

        $actual = trim($compiler->callPhpizeExpression('addDollarIfNeeded', 'a'));
        $this->assertSame('$a', $actual);
    }

    public function testJsLanguageOptions()
    {
        $pug = new Pug([
            'debug' => true,
            'expressionLanguage' => 'js',
            'jsLanguage' => [
                'helpers' => [
                    'dot' => 'plus',
                ],
            ],
        ]);

        $actual = trim($pug->render('=a.ho', [
            'a' => 'hi '
        ]));
        $this->assertSame('hi ho', $actual);
    }
}
