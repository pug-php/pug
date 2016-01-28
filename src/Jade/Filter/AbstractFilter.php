<?php

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

/**
 * Class Jade\Filter\AbstractFilter.
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * Returns the node string value, line by line.
     * If the compiler is present, that means we need
     * to interpolate line contents.
     *
     * @param Filter   $node
     * @param Compiler $compiler
     *
     * @return mixed
     */
    protected function getNodeString(Filter $node, Compiler $compiler = null)
    {
        return array_reduce($node->block->nodes, function (&$result, $line) use ($compiler) {
            $val = $compiler ? $compiler->interpolate($line->value) : $line->value;

            return $result .= $val . "\n";
        });
    }
}
