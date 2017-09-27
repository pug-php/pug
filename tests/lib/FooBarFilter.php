<?php

namespace Pug\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

class FooBar extends AbstractFilter
{
    public function parse($code)
    {
        return strtr(strtoupper($code), array(
            '(' => ')',
            'SMALL' => 'TALL',
        ));
    }

}
