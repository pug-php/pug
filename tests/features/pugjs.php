<?php

use Pug\Pug;

class PugJsTest extends PHPUnit_Framework_TestCase
{
    public function testPugJsOption()
    {
        $pug = new Pug(array(
            'pugjs' => true,
        ));

        $html = $pug->render('h1=name', array('name' => 'Yop'));

        $this->assertSame('<h1>Yop</h1>', $html);

        $html = $pug->render(__DIR__ . '/../templates/basic.jade');

        $this->assertSame('<html><body><h1>Title</h1></body></html>', $html);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Pugjs throw an error
     */
    public function testPugJsOptionException()
    {
        $pug = new Pug(array(
            'pugjs' => true,
        ));

        $pug->render('./bar/basic.jade');
    }
}
