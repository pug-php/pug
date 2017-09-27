<?php

namespace Pug\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

class Escaped extends AbstractFilter
{
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return htmlentities($this->getNodeString($node, $compiler));
    }

    public function parse($code)
    {
        return htmlentities($code);
    }
}
