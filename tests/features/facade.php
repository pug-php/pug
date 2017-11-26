<?php

use PHPUnit\Framework\TestCase;
use Pug\Facade as Pug;

class FacadeTest extends TestCase
{
    public function testFacade()
    {
        $html = Pug::render('p=foo', ['foo' => 'bar']);
        self::assertSame('<p>bar</p>', $html);

        Pug::share('pi', 3.14);
        $html = Pug::render('=2 * pi');
        self::assertSame('6.28', $html);
    }
}
