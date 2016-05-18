<?php

use Jade\Jade;
use Pug\Pug;

class PugAliasTest extends PHPUnit_Framework_TestCase {

    /**
     * test the Pug alias
     */
    public function testPugAlias() {

        $jade = new Jade();
        $pug = new Pug();

        $this->assertSame($jade->getOption('stream'), 'jade.stream');
        $this->assertSame($pug->getOption('stream'), 'pug.stream');
        $this->assertSame($pug->render('p Hello'), '<p>Hello</p>');
        $this->assertSame($pug->getExtension(), '.pug');

        $jade = new Jade(array(
            'extension' => '.foo',
        ));
        $this->assertSame($jade->getExtension(), '.foo');

        $jade->setOption('extension', array('.jade', '.pug'));
        $this->assertSame($jade->getExtension(), '.jade');

        $jade->setOption('extension', array());
        $this->assertSame($jade->getExtension(), '');
    }
}
