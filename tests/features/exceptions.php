<?php

use Jade\Parser;

class EmulateBugException extends \Exception {}

class JadeExceptionsTest extends PHPUnit_Framework_TestCase {

    static public function emulateBug() {

        throw new EmulateBugException("Error Processing Request", 1);
    }
    /**
     * @expectedException Exception
     */
    public function testDoNotUnderstand() {

        get_php_code('a(href=="a")');
    }

    /**
     * @expectedException Exception
     */
    public function testCannotBeReadFromPhp() {

        get_php_code('- var foo = Inf' . "\n" . 'p=foo');
    }

    /**
     * @expectedException Exception
     */
    public function testUnexpectingValue() {

        get_php_code('a(href="foo""bar")');
    }

    /**
     * @expectedException Exception
     */
    public function testExpectedIndent() {

        get_php_code(':a+()');
    }

    /**
     * @expectedException Exception
     */
    public function testUnexpectingToken() {

        get_php_code('a:' . "\n" . '!!!5');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testExceptionThroughtJade() {

        get_php_code('a(href=\JadeExceptionsTest::emulateBug())');
    }

    /**
     * @expectedException Exception
     */
    public function testNonParsableExtends() {

        get_php_code(__DIR__ . '/../templates/auxiliary/extends-failure.jade');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testBrokenExtends() {

        get_php_code(__DIR__ . '/../templates/auxiliary/extends-exception.jade');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFilterDoesNotExist() {

        get_php_code(':foo' . "\n" . '  | Foo language');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testBrokenInclude() {

        get_php_code(__DIR__ . '/../templates/auxiliary/include-exception.jade');
    }
}
