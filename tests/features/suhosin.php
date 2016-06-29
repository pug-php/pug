<?php

use Pug\Pug;

class PugSuhosin extends Pug
{
    protected function suhosinWhiteListNeeded()
    {
        return true;
    }

}

class JadeSuhosinTest extends PHPUnit_Framework_TestCase
{
    public function testSuhosinEnabled()
    {
        $pug = new PugSuhosin();
        $message = '';
        $code = null;
        try {
            $pug->render('h1 Hello');
        } catch (\ErrorException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
        }
        $this->assertTrue(false !== strpos($message, 'suhosin.executor.include.whitelist'), 'Error should contain suhosin.executor.include.whitelist.');
        $this->assertTrue(false !== strpos($message, 'pug.stream'), 'Error should contain pug.stream.');
        $this->assertSame(4, $code, 'The error code should be 4');
    }
}
