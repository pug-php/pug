<?php

use Pug\Compiler;
use Pug\Pug;

class PugCompilerOptionsTest extends PHPUnit_Framework_TestCase
{
    public function testArrayOptions()
    {
        $compiler = new Compiler(array(
            'allowMixinOverride' => true,
            'indentChar' => '-',
        ));
        $this->assertTrue($compiler->getOption('allowMixinOverride'));
        $this->assertSame('-', $compiler->getOption('indentChar'));
    }

    public function testEngineOptions()
    {
        $Pug = new Pug(array(
            'terse' => false,
            'indentChar' => '@',
        ));
        $compiler = new Compiler($Pug);
        $Pug->setCustomOption('foo', 'bar');
        $this->assertFalse($compiler->getOption('terse'));
        $this->assertSame('@', $compiler->getOption('indentChar'));
        $this->assertSame('bar', $compiler->getOption('foo'));
    }

    public function testCompilerGetFilename()
    {
        $compiler = new Compiler(array(), array(), 'foobar');
        $this->assertSame('foobar', $compiler->getFilename());
    }
}
