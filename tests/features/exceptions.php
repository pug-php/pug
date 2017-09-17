<?php

use Pug\Parser;
use Pug\Pug;

class EmulateBugException extends \Exception {}
class OnlyOnceException extends \Exception {}

class PugExceptionsTest extends PHPUnit_Framework_TestCase
{
    static public function emulateBug()
    {
        throw new EmulateBugException("Error Processing Request", 1);
    }

    /**
     * @expectedException \Exception
     */
    public function testDoNotUnderstand()
    {
        get_php_code('a(href=="a")');
    }

    /**
     * @expectedException \Exception
     */
    public function testDoubleDoubleArrow()
    {
        get_php_code('a(href=["a" => "b" => "c"])');
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

    public function testUnexpectedValuePreviousException()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not compatible with HHVM');
        }

        $code = null;
        try {
            get_php_code('a(href="foo""bar")');
        } catch (\Exception $e) {
            $code = $e->getPrevious()->getCode();
        }

        $this->assertSame(8, $code, 'Expected previous exception code should be 8 for UnexpectedValue.');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 21
     */
    public function testUnableToFindAttributesClosingParenthesis()
    {
        get_php_code('a(href=');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 24
     */
    public function testExpectedIndent()
    {
        get_php_code(':a()');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 25
     */
    public function testUnexpectingToken()
    {
        get_php_code('a:' . "\n" . '!!!5');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testExceptionThroughtPug()
    {
        get_php_code('a(href=\PugExceptionsTest::emulateBug())');
    }

    /**
     * @expectedException Pug\Parser\Exception
     * @expectedExceptionCode 10
     */
    public function testNonParsableExtends()
    {
        get_php_code(__DIR__ . '/../templates/auxiliary/extends-failure.pug');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testBrokenExtends()
    {
        get_php_code(__DIR__ . '/../templates/auxiliary/extends-exception.pug');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 3
     */
    public function testSetInvalidOption()
    {
        $pug = new Pug();
        $pug->setOption('i-do-not-exists', 'wrong');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 3
     */
    public function testSetInvalidOptions()
    {
        $pug = new Pug();
        $pug->setOptions(array(
            'prettyprint' => true,
            'i-do-not-exists' => 'right',
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 2
     */
    public function testGetInvalidOption()
    {
        $pug = new Pug();
        $pug->getOption('i-do-not-exists');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testExtendsWithFilterException()
    {
        $pug = new Pug();
        $pug->filter('throw-exception', function () {
            throw new EmulateBugException("Bad filter", 1);
        });
        $pug->render(__DIR__ . '/../templates/auxiliary/extends-exception-filter.pug');
    }

    /**
     * Test OnlyOnceException
     */
    public function testExtendsWithParserException()
    {
        $parser = new ExtendParser(__DIR__ . '/../templates/auxiliary/extends-exception-filter.pug');
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
    public function testIncludesWithParserException()
    {
        $parser = new IncludeParser(__DIR__ . '/../templates/auxiliary/include-exception-filter.pug');
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
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 17
     */
    public function testFilterDoesNotExist()
    {
        get_php_code(':foo' . "\n" . '  | Foo language');
    }

    /**
     * @expectedException EmulateBugException
     */
    public function testBrokenInclude()
    {
        get_php_code(__DIR__ . '/../templates/auxiliary/include-exception.pug');
    }
}
