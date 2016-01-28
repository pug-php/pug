<?php

class JadeExceptionsTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException Exception
     */
    public function testUnexpectingToken() {

        get_php_code('a(href=="a")');
    }

    /**
     * @expectedException Exception
     */
    public function testExceptionThroughtJade() {

        get_php_code('a(href="a" . (throw new Exception("Error Processing Request", 1)) . "b")');
    }
}
