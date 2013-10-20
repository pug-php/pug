<?php

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

/**
 * Class Javascript
 * @package Jade\Filter
 */
class Javascript extends AbstractFilter {

    /**
     * @param Filter   $node
     * @param Compiler $compiler
     *
     * @return string
     */
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return '<script type="text/javascript">'.$this->getNodeString($node, $compiler).'</script>';
    }
}