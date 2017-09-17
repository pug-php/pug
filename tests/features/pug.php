<?php

use Pug\Pug;

class PugAliasTest extends PHPUnit_Framework_TestCase
{
    /**
     * test the Pug alias
     */
    public function testPugAlias()
    {
        $pug = new Pug();

        $this->assertSame($pug->getDefaultOption('stream'), 'pug.stream');
        $this->assertSame($pug->render('p Hello'), '<p>Hello</p>');
        $this->assertSame($pug->getExtension(), '.pug');
        $this->assertTrue(in_array('.pug', $pug->getExtensions()));

        $pug = new Pug(array(
            'extension' => '.foo',
        ));
        $this->assertSame($pug->getExtension(), '.foo');
        $this->assertFalse(in_array('.pug', $pug->getExtensions()));
        $this->assertTrue(in_array('.foo', $pug->getExtensions()));

        $pug->setOption('extension', array('.jade', '.pug'));
        $this->assertSame($pug->getExtension(), '.jade');
        $this->assertFalse(in_array('.foo', $pug->getExtensions()));
        $this->assertTrue(in_array('.jade', $pug->getExtensions()));
        $this->assertTrue(in_array('.pug', $pug->getExtensions()));

        $pug->setOption('extension', array());
        $this->assertSame($pug->getExtension(), '');
        $this->assertFalse(in_array('', $pug->getExtensions()));
        $this->assertFalse(in_array('.foo', $pug->getExtensions()));
        $this->assertFalse(in_array('.jade', $pug->getExtensions()));
        $this->assertFalse(in_array('.pug', $pug->getExtensions()));

        $pug->setOption('extension', '.pug');
        $this->assertSame($pug->getExtension(), '.pug');
        $this->assertFalse(in_array('', $pug->getExtensions()));
        $this->assertFalse(in_array('.foo', $pug->getExtensions()));
        $this->assertFalse(in_array('.jade', $pug->getExtensions()));
        $this->assertTrue(in_array('.pug', $pug->getExtensions()));
    }
}
