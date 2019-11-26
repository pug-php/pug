<?php

use Phug\CompilerException;
use Phug\LexerException;
use Pug\Pug;
use Pug\Test\AbstractTestCase;

class EmulateBugException extends Exception {}

class PugExceptionsTest extends AbstractTestCase
{
    static public function emulateBug()
    {
        throw new EmulateBugException("Error Processing Request", 1);
    }

    public function testAbsoluteIncludeWithNoBaseDir()
    {
        self::expectException(Exception::class);

        $pug = new Pug();
        $pug->render('include /auxiliary/world');
    }

    public function testCannotBeReadFromPhp()
    {
        self::expectException(Exception::class);

        get_php_code('- var foo = Inf' . "\n" . 'p=foo');
    }

    public function testUnexpectedValue()
    {
        self::expectException(Exception::class);

        get_php_code('a(href="foo""bar")');
    }

    public function testUnableToFindAttributesClosingParenthesis()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Unclosed attribute block');

        get_php_code('a(href=');
    }

    public function testExceptionThroughPug()
    {
        self::expectException(Exception::class);
        self::expectExceptionMessage('Error Processing Request');

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'expressionLanguage' => 'php',
        ]);
        $pug->render('a(href=\PugExceptionsTest::emulateBug())');
    }

    public function testNonParsableExtends()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('The syntax for each is');

        get_php_file(__DIR__ . '/../templates/auxiliary/extends-failure.pug');
    }

    public function testBrokenExtends()
    {
        self::expectException(Exception::class);
        self::expectExceptionMessage('Error Processing Request');

        get_php_file(__DIR__ . '/../templates/auxiliary/extends-exception.pug');
    }

    /**
     * @group filters
     */
    public function testExtendsWithFilterException()
    {
        self::expectException(Exception::class);
        self::expectExceptionMessage('Bad filter');

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        $pug->filter('throw-exception', function () {
            throw new EmulateBugException('Bad filter', 1);
        });
        $pug->renderFile(__DIR__ . '/../templates/auxiliary/extends-exception-filter.pug');
    }

    public function testFilterDoesNotExist()
    {
        self::expectException(CompilerException::class);
        self::expectExceptionMessage('Unknown filter foo');

        get_php_code(':foo' . "\n" . '  | Foo language');
    }

    public function testBrokenInclude()
    {
        self::expectException(Exception::class);
        self::expectExceptionMessage('Error Processing Request');

        get_php_file(__DIR__ . '/../templates/auxiliary/include-exception.pug');
    }
}
