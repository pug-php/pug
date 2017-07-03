<?php

use Pug\Pug;

class ShareTest extends PHPUnit_Framework_TestCase
{
    public function testShare()
    {
        $Pug = new Pug();
        $Pug->share('answear', 42);
        $Pug->share(array(
            'foo' => 'Hello',
            'bar' => 'world',
        ));
        $html = $Pug->render("p=foo\ndiv=answear");
        $this->assertSame('<p>Hello</p><div>42</div>', $html);

        $html = $Pug->render("p=foo\ndiv=answear", array(
            'answear' => 16,
        ));
        $this->assertSame('<p>Hello</p><div>16</div>', $html);

        $html = $Pug->render("p\n  =foo\n  =' '\n  =bar\n  | !");
        $this->assertSame('<p>Hello world!</p>', $html);
    }

    public function testResetSharedVariables()
    {
        $Pug = new Pug();
        $Pug->share('answear', 42);
        $Pug->share(array(
            'foo' => 'Hello',
            'bar' => 'world',
        ));
        $Pug->resetSharedVariables();

        $error = null;
        try {
            $Pug->render("p\n  =foo\n=' '\n=bar\n  | !");
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $this->assertSame('Undefined variable: foo', $error);
    }
}
