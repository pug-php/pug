<?php

use Pug\Pug;
use Pug\Test\AbstractTestCase;

class PugHooksTest extends AbstractTestCase
{
    public function testPreRender()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'preRender' => function ($pugCode) {
                return preg_replace('/\*\*btn/', 'class="btn btn-primary" data-button="on"', $pugCode);
            },
        ]);
        $html = $pug->render('a#foo.bar(**btn title="Foo") Hello');
        $expected = '<a id="foo" class="bar btn btn-primary" data-button="on" title="Foo">Hello</a>';

        self::assertSame($expected, $html);
    }

    public function testPreRenderIncludeAndExtend()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'basedir' => __DIR__ . '/../templates/auxiliary',
            'preRender' => function ($pugCode) {
                return str_replace(
                    ['My Application', 'case 42'],
                    ['Foobar', 'case 1138'],
                    $pugCode
                );
            },
        ]);
        $html = preg_replace('/\s/', '',$pug->render(
            'extends /layout' . "\n" .
            'block content' . "\n" .
            '  include /world'
        ));
        $expected = preg_replace('/\s/', '',
            '<html>' .
            '  <head>' .
            '    <title>Foobar</title>' .
            '  </head>' .
            '  <body>' .
            '    <p>THX</p>' .
            '  </body>' .
            '</html>'
        );

        self::assertSame($expected, $html);
    }

    public function testPostRender()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'postRender' => function ($phpCode) {
                return str_replace('<a', '<a data-contains-attributes="true"', $phpCode);
            },
        ]);
        $html = $pug->render('a#foo(title=5*3) Hello');
        $expected = '<a data-contains-attributes="true" id="foo" title="15">Hello</a>';

        self::assertSame($expected, $html);
    }

    public function testEventsOrder()
    {
        $pug = new Pug([
            'on_lex' => function (\Phug\Lexer\Event\LexEvent $event) {
                $event->setInput(str_replace('#foo', '#bar', $event->getInput()));
            },
            'preRender' => function ($pugCode) {
                return str_replace('#bar', '.bar', $pugCode);
            },
            'on_output' => function (\Phug\Compiler\Event\OutputEvent $event) {
                $event->setOutput(str_replace('bar', 'baz', $event->getOutput()));
            },
            'postRender' => function ($phpCode) {
                return str_replace('baz', 'biz', $phpCode);
            },
        ]);
        $html = $pug->render('#foo');
        $expected = '<div class="biz"></div>';

        self::assertSame($expected, $html);
    }
}
