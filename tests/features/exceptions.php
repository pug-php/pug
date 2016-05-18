<?php

use Jade\Parser;
use Jade\Jade;

class EmulateBugException extends \Exception {}
class OnlyOnceException extends \Exception {}

class ExtendParser extends Parser {

    public function parse() {

        static $i = 0;
        if ($i++) {
            throw new OnlyOnceException("E: Works only once", 1);
        }
        parent::parse();
    }
}

class IncludeParser extends Parser {

    public function parse() {

        static $i = 0;
        if ($i++) {
            throw new OnlyOnceException("I: Works only once", 1);
        }
        parent::parse();
    }
}

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
    public function testBadInclude() {

        get_php_code('include a/file/with/an.extension');
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
    public function testSetInvalidOption() {

        $jade = new Jade();
        $jade->setOption('i-do-not-exists', 'wrong');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetInvalidOptions() {

        $jade = new Jade();
        $jade->setOptions(array(
            'prettyprint' => true,
            'i-do-not-exists' => 'right',
        ));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetInvalidOption() {

        $jade = new Jade();
        $jade->getOption('i-do-not-exists');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testExtendsWithFilterException() {

        $jade = new Jade();
        $jade->filter('throw-exception', function () {
            throw new EmulateBugException("Bad filter", 1);
        });
        $jade->render(__DIR__ . '/../templates/auxiliary/extends-exception-filter.jade');
    }

    /**
     * Test OnlyOnceException
     */
    public function testExtendsWithParserException() {

        $parser = new ExtendParser(__DIR__ . '/../templates/auxiliary/extends-exception-filter.jade');
        $message = null;
        try {
            $parser->parse();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $this->assertTrue($message !== null, 'Extends with ExtendParser should throw an exception');
        $this->assertTrue(strpos($message, 'E: Works only once') !== false, 'Extends with ExtendParser should throw an exception with the initial message of the exception inside');
    }

    /**
     * Test OnlyOnceException
     */
    public function testIncludesWithParserException() {

        $parser = new IncludeParser(__DIR__ . '/../templates/auxiliary/include-exception-filter.jade');
        $message = null;
        try {
            $parser->parse();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $this->assertTrue($message !== null, 'Extends with IncludeParser should throw an exception');
        $this->assertTrue(strpos($message, 'I: Works only once') !== false, 'Include with IncludeParser should throw an exception with the initial message of the exception inside');
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
