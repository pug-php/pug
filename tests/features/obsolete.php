<?php

class JadeFilter extends \Jade\Filter\AbstractFilter
{
    // Obsolete
}

class NodeStringFilter extends \Pug\Filter\AbstractFilter
{
    public function test()
    {
        $this->getNodeString();
    }
}

class PugObsoleteTest extends PHPUnit_Framework_TestCase
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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage ->getNodeString is no longer supported since you get now contents as a string.
     */
    public function testGetNodeString()
    {
        $filter = new NodeStringFilter();
        $filter->test();
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionMessage ->stream() is no longer available
     */
    public function testStream()
    {
        $pug = new \Pug\Pug();
        $pug->stream();
    }
}
