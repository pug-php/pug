<?php

use Pug\Pug;

class JadeHooksTest extends PHPUnit_Framework_TestCase
{
    public function testPreRender()
    {
        $pug = new Pug(array(
            'preRender' => function ($pugCode) {
                return preg_replace('/\*\*btn/', 'class="btn btn-primary" data-button="on"', $pugCode);
            },
        ));
        $html = $pug->render('a#foo.bar(**btn title="Foo") Hello');
        $expected = '<a id="foo" data-button="on" title="Foo" class="bar btn btn-primary">Hello</a>';

        $this->assertSame($expected, $html);
    }

    public function testPostRender()
    {
        $pug = new Pug(array(
            'postRender' => function ($phpCode) {
                return preg_replace('/<\?php.*\?>(?<!<\?php)/', '$0 data-dynamic="true"', $phpCode);
            },
        ));
        $html = $pug->render('a#foo(title=5*3) Hello');
        $expected = '<a id="foo" title="15" data-dynamic="true">Hello</a>';

        $this->assertSame($expected, $html);
    }
}
