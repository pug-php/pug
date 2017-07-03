<?php

namespace Jade\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

/**
 * Class Pug\Filter\Javascript.
 */
class Javascript extends AbstractFilter
{
    /**
     * @param Filter   $node
     * @param Compiler $compiler
     *
     * @return string
     */
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return '<script type="text/javascript">' . $this->getNodeString($node, $compiler) . '</script>';
    }
}
