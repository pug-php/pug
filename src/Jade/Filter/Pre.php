<?php

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

class Pre extends AbstractFilter
{
    protected $tag = 'pre';

    public function parse($contents)
    {
        return htmlspecialchars(trim($contents));
    }
}
