<?php

use PHPUnit\Framework\TestCase;
use Pug\Parser;
use Pug\Pug;

class EmulateBugException extends \Exception {}
class OnlyOnceException extends \Exception {}

class PugExceptionsTest extends TestCase
{
    static public function emulateBug()
    {
        throw new EmulateBugException("Error Processing Request", 1);
    }

    /**
     * @expectedException \Exception
     */
    public function testAbsoluteIncludeWithNoBaseDir()
    {
        $pug = new Pug();
        $pug->render('include /auxiliary/world');
    }

    /**
     * @expectedException \Exception
     */
    public function testCannotBeReadFromPhp()
    {
        get_php_code('- var foo = Inf' . "\n" . 'p=foo');
    }

    /**
     * @expectedException \Exception
     */
    public function testUnexpectedValue()
    {
        get_php_code('a(href="foo""bar")');
    }
    /**
     * @expectedException \Phug\LexerException
     * @expectedExceptionMessage Unclosed attribute block
     */
    public function testUnableToFindAttributesClosingParenthesis()
    {
        get_php_code('a(href=');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Error Processing Request
     */
    public function testExceptionThroughtPug()
    {
        $pug = new Pug([
            'expressionLanguage' => 'php',
        ]);
        $pug->render('a(href=\PugExceptionsTest::emulateBug())');
    }

    /**
     * @expectedException \Phug\LexerException
     * @expectedExceptionMessage The syntax for each is
     */
    public function testNonParsableExtends()
    {
        get_php_file(__DIR__ . '/../templates/auxiliary/extends-failure.pug');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Error Processing Request
     */
    public function testBrokenExtends()
    {
        get_php_file(__DIR__ . '/../templates/auxiliary/extends-exception.pug');
    }

    /**
     * @group filters
     * @expectedException \Exception
     * @expectedExceptionMessage Bad filter
     */
    public function testExtendsWithFilterException()
    {
        $pug = new Pug();
        $pug->filter('throw-exception', function () {
            throw new EmulateBugException('Bad filter', 1);
        });
        $pug->renderFile(__DIR__ . '/../templates/auxiliary/extends-exception-filter.pug');
    }

    /**
     * @expectedException \Phug\CompilerException
     * @expectedExceptionMessage Unknown filter foo
     */
    public function testFilterDoesNotExist()
    {
        get_php_code(':foo' . "\n" . '  | Foo language');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Error Processing Request
     */
    public function testBrokenInclude()
    {
        get_php_file(__DIR__ . '/../templates/auxiliary/include-exception.pug');
    }
}
