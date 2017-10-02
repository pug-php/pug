<?php

namespace Pug\Filter;

use Pug\AbstractFilter as FilterBase;

class Cdata extends FilterBase
{
    public function parse($code)
    {
        return "<![CDATA[\n$code\n]]>";
    }
}
