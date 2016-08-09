<?php

namespace Jade\Lexer;

use Jade\Compiler\CommonUtils;

/**
 * Class Jade\Lexer\AttributesState.
 */
class AttributesState
{
    protected $states = array('key');

    public function __construct()
    {
        # code...
    }

    public function current()
    {
        return $this->states[count($this->states) - 1];
    }

    public function is()
    {
        return in_array($this->current(), func_get_args());
    }

    public function pop()
    {
        array_pop($this->states);
    }

    public function push($value)
    {
        array_push($this->states, $value);
    }

    public function popFor()
    {
        if (call_user_func_array(array($this, 'is'), func_get_args())) {
            $this->pop();
        }
    }

    public function pushFor($value)
    {
        if (call_user_func_array(array($this, 'is'), array_slice(func_get_args(), 1))) {
            $this->push($value);
        }
    }
}
