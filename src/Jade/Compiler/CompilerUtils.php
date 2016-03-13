<?php

namespace Jade\Compiler;

/**
 * Class Jade CompilerUtils.
 * Internal static methods of the compiler.
 */
abstract class CompilerUtils extends Indenter
{
    /**
     * @param string $call
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function addDollarIfNeeded($call)
    {
        if ($call === 'Inf') {
            throw new \Exception($call . ' cannot be read from PHP', 1);
        }
        if ($call === 'undefined') {
            return 'null';
        }
        if ($call[0] !== '$' && $call[0] !== '\\' && !preg_match('#^(?:' . static::VARNAME . '\\s*\\(|(?:null|false|true)(?![a-z]))#i', $call)) {
            $call = '$' . $call;
        }

        return $call;
    }

    /**
     * Concat " = null" to initializations to simulate the JS "var foo;".
     *
     * @param &string reference of an argument containing an expression
     *
     * @throws \Exception
     */
    protected static function initArgToNull(&$arg)
    {
        $arg = static::addDollarIfNeeded(trim($arg));
        if (strpos($arg, '=') === false) {
            $arg .= ' = null';
        }
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected static function parseValue($value)
    {
        return json_decode(preg_replace("/'([^']*?)'/", '"$1"', $value));
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected static function decodeValue($value)
    {
        $_value = static::parseValue($value);

        return is_null($_value) ? $value : $_value;
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    protected static function decodeAttributes($attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_array($attribute)) {
                $attribute['value'] = static::decodeValue($attribute['value']);
            } else {
                $attribute = static::decodeValue($attribute);
            }
        }

        return $attributes;
    }
}
