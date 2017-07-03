<?php

namespace Jade\Filter;

abstract class AbstractFilter
{
    function __construct()
    {
        throw new \InvalidArgumentException(
            'Jade namespace is no longer available, use Pug instead.'
        );
    }
}
