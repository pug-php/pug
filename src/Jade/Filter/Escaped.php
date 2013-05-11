<?php

namespace Jade\Filter;

class Escaped extends FilterAbstract {

    public function __invoke($node, $compiler)
    {
        return htmlentities($this->getNodeString($node, $compiler));
    }

}