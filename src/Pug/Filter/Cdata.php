<?php

namespace Pug\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

class Cdata extends AbstractFilter
{
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return "<![CDATA[\n" . $this->getNodeString($node, $compiler) . "\n]]>";
    }

    public function parse($code)
    {
        return "<![CDATA[\n$code\n]]>";
    }
}
