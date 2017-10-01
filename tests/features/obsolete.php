<?php

class JadeFilter extends \Jade\Filter\AbstractFilter
{
    // Obsolete
}

class PugExceptionsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Jade namespace is no longer available, use Pug instead.
     */
    public function testJadeCompiler()
    {
        new \Jade\Compiler();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Jade namespace is no longer available, use Pug instead.
     */
    public function testJadeJade()
    {
        new \Jade\Jade();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Jade namespace is no longer available, use Pug instead.
     */
    public function testJadeLexer()
    {
        new \Jade\Lexer();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Jade namespace is no longer available, use Pug instead.
     */
    public function testJadeParser()
    {
        new \Jade\Parser();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Jade namespace is no longer available, use Pug instead.
     */
    public function testJadeFilter()
    {
        new JadeFilter();
    }
}
