<?php

use Pug\Pug;

class JadeIssuesTest extends PHPUnit_Framework_TestCase
{
    public function testIssue62()
    {
        $pug = new Pug();
        $html = trim($pug->render('.MyInitialClass(class=$classes)', array(
            'classes' => 'MyClass',
        )));
        $expected = '<div class="MyInitialClass MyClass"></div>';

        $this->assertSame($expected, $html);
    }

    public function testIssue64()
    {
        $pug = new Pug();
        $html = trim($pug->render("script.\n" . '  var url = "/path/#{$foo->bar}/file";', array(
            'foo' => (object) array(
                'bar' => 'hello/world',
            ),
        )));
        $expected = '<script>var url = "/path/hello/world/file";</script>';

        $this->assertSame($expected, $html);
    }
}
