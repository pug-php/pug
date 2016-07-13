<?php

namespace Jade\Filter;

class Pre extends AbstractFilter
{
    protected $tag = 'pre';

    public function parse($contents)
    {
        return htmlspecialchars(trim($contents));
    }
}
