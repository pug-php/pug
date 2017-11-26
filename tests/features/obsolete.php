<?php

use PHPUnit\Framework\TestCase;

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

include_once __DIR__ . '/../lib/LegacyFilterNode.php';

class PugObsoleteTest extends TestCase
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
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Pug\Filter\FilterInterface is no longer supported. Now use Pug\FilterInterface instead.
     */
    public function testInvoke()
    {
        $filter = new NodeStringFilter();
        $filter(new \Pug\Nodes\Filter(), new \Pug\Compiler());
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
