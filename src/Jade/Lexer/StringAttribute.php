<?php

namespace Jade\Lexer;

/**
 * Class Jade\Lexer\StringAttribute.
 */
class StringAttribute
{
    protected $state;
    protected $char;

    public function __construct($state, $char)
    {
        $this->state = $state;
        $this->char = $char;
    }

    public function parse(&$states, &$val, &$quote)
    {
        $state = $this->state;
        switch ($state()) {
            case 'key':
                array_push($states, 'key char');
                break;

            case 'key char':
                array_pop($states);
                break;

            case 'string':
                if ($this->char === $quote) {
                    array_pop($states);
                }
                $val .= $this->char;
                break;

            default:
                array_push($states, 'string');
                $val .= $this->char;
                $quote = $this->char;
                break;
        }
    }
}
