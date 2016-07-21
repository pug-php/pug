<?php

use Jade\Jade;

class JadeWhiteSpaceTest extends PHPUnit_Framework_TestCase
{
    public function testTextarea()
    {
        $jade = new Jade();

        $actual = $jade->render("div\n  textarea");
        $expected = '<div><textarea></textarea></div>';
        $this->assertSame($expected, $actual);

        $actual = $jade->render("div\n  textarea Bob");
        $expected = '<div><textarea>Bob</textarea></div>';
        $this->assertSame($expected, $actual);

        $actual = $jade->render("textarea\n  ='Bob'");
        $expected = '<textarea>Bob</textarea>';
        $this->assertSame($expected, $actual);

        $actual = $jade->render("div\n  textarea.\n    Bob\n    Boby");
        $expected = "<div><textarea>Bob\nBoby</textarea></div>";
        $this->assertSame($expected, $actual);

        $actual = $jade->render("textarea\n  | Bob");
        $expected = '<textarea>Bob</textarea>';
        $this->assertSame($expected, $actual);
    }
}
