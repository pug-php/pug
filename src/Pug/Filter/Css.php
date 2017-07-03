<?php

namespace Pug\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

class Css extends AbstractFilter
{
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return '<style type="text/css">' . $this->getNodeString($node, $compiler) . '</style>';
    }
}
