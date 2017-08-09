<?php

namespace Pug\Lexer;

/**
 * Class Pug\Lexer\AttributesState.
 */
class AttributesState
{
    protected $states = ['key'];

    public function current()
    {
        return $this->states[count($this->states) - 1];
    }

    protected function isIn()
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
        if (call_user_func_array([$this, 'isIn'], func_get_args())) {
            $this->pop();
        }
    }

    public function pushFor($value)
    {
        if (call_user_func_array([$this, 'isIn'], array_slice(func_get_args(), 1))) {
            $this->push($value);
        }
    }
}
