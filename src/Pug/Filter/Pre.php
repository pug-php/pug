<?php

namespace Pug\Filter;

/**
 * Class Pug\Filter\Pre.
 */
class Pre extends AbstractFilter
{
    protected $tag = 'pre';

    public function parse($contents)
    {
        return htmlspecialchars(trim($contents));
    }
}
