<?php

use Pug\Pug;
use Pug\Test\AbstractTestCase;

class ShareTest extends AbstractTestCase
{
    public function testShare()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);

        $pug->share('answear', 42);
        $pug->share([
            'foo' => 'Hello',
            'bar' => 'world',
        ]);
        $html = $pug->render("p=\$foo\ndiv=\$answear");
        self::assertSame('<p>Hello</p><div>42</div>', $html);

        $html = $pug->render("p=\$foo\ndiv=\$answear", [
            'answear' => 16,
        ]);
        self::assertSame('<p>Hello</p><div>16</div>', $html);

        $html = $pug->render("p\n  ?=\$foo\n  =' '\n  =\$bar\n  | !");
        self::assertSame('<p>Hello world!</p>', $html);
    }

    public function testResetSharedVariables()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        $pug->share('answear', 42);
        $pug->share([
            'foo' => 'Hello',
            'bar' => 'world',
        ]);
        $pug->resetSharedVariables();

        $error = null;
        try {
            $pug->render("p\n  ?=\$foo\n=' '\n=\$bar\n  | !");
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        self::assertRegExp('/Undefined variable: foo/', $error);
    }
}
