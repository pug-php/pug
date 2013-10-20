<?php

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

/**
 * Interface FilterInterface
 * @package Jade\Filter
 */
interface FilterInterface
{
    public function __invoke(Filter $filter, Compiler $compiler);
}