<?php

use Pug\Pug;
use Pug\Test\AbstractTestCase;

class PugWhiteSpaceTest extends AbstractTestCase
{
    public function testTextarea()
    {
        $pug = new Pug();

        $actual = $pug->render("div\n  textarea");
        $expected = '<div><textarea></textarea></div>';
        self::assertSame($expected, $actual);

        $actual = $pug->render("div\n  textarea Bob");
        $expected = '<div><textarea>Bob</textarea></div>';
        self::assertSame($expected, $actual);

        $actual = $pug->render("textarea\n  ='Bob'");
        $expected = '<textarea>Bob</textarea>';
        self::assertSame($expected, $actual);

        $actual = $pug->render("div\n  textarea.\n    Bob\n    Boby");
        $expected = "<div><textarea>Bob\nBoby</textarea></div>";
        self::assertSame($expected, $actual);

        $actual = $pug->render("textarea\n  | Bob");
        $expected = '<textarea>Bob</textarea>';
        self::assertSame($expected, $actual);
    }

    public function testPipeless()
    {
        $pug = new Pug([
            'prettyprint' => false,
        ]);

        $actual = $pug->render("div\n  span.
            Some indented text
            on many lines
            but the words
            must not
            be
            sticked.
        ");
        $expected = '<div><span>Some indented text on many lines but the words must not be sticked. </span></div>';
        $actual = preg_replace('/\s+/', ' ', $actual);
        self::assertSame($expected, $actual);
    }
}
