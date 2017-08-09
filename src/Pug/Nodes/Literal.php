<?php

namespace Pug\Nodes;

class Literal extends Node
{
    public $string;

    public function __construct($string)
    {
        // escape the chars '\', '\n', '\r\n' and "'"
        $this->string = preg_replace(['/\\\\/', '/\\n|\\r\\n/', '/\'/'], ['\\\\', "\r", "\\'"], $string);
    }
}
