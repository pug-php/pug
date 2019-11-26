<?php

use Jade\Compiler;
use Jade\Jade;
use Jade\Lexer;
use Jade\Parser;
use Pug\Test\AbstractTestCase;

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

class PugObsoleteTest extends AbstractTestCase
{
    public function getObsoleteClasses()
    {
        return [
            [Compiler::class],
            [Jade::class],
            [Lexer::class],
            [Parser::class],
            [JadeFilter::class],
        ];
    }

    /**
     * @dataProvider getObsoleteClasses
     */
    public function testJadeClassUse($class)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Jade namespace is no longer available, use Pug instead.');

        new $class();
    }

    public function testGetNodeString()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('->getNodeString is no longer supported since you get now contents as a string.');

        $filter = new NodeStringFilter();
        $filter->test();
    }

    public function testInvoke()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Pug\Filter\FilterInterface is no longer supported. Now use Pug\FilterInterface instead.');

        $filter = new NodeStringFilter();
        $filter(new \Pug\Nodes\Filter(), new \Pug\Compiler());
    }

    public function testStream()
    {
        self::expectException(ErrorException::class);
        self::expectExceptionMessage('->stream() is no longer available');

        $pug = new \Pug\Pug();
        $pug->stream();
    }
}
