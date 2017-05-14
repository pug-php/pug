<?php

namespace Pug\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

/**
 * Interface Pug\Filter\FilterInterface.
 */
interface FilterInterface
{
    public function __invoke(Filter $filter, Compiler $compiler);
}
