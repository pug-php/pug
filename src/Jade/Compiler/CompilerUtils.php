<?php

namespace Jade\Compiler;

/**
 * Class Jade CompilerUtils.
 * Internal static methods of the compiler.
 */
abstract class CompilerUtils extends Indenter
{
    /**
     * Prepend "$" to the given input if it's a varname.
     *
     * @param $call string
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected static function addDollarIfNeeded($call)
    {
        return CommonUtils::addDollarIfNeeded($call);
    }

    /**
     * Escape value depanding on the current quote.
     *
     * @param string  input value
     *
     * @return string
     */
    protected function escapeValue($val)
    {
        return static::getEscapedValue($val, $this->quote);
    }

    /**
     * Return PHP code to translate dot to object/array getter.
     *
     * @example foo.bar return $foo->bar (if foo is an object), or $foo["bar"] if it's an array.
     *
     * @param $match array regex match
     *
     * @return string
     */
    protected static function convertVarPathCallback($match)
    {
        if (empty($match[1])) {
            return $match[0];
        }

        $var = ($match[0] === ',' ? ',' : '') . $match[1];
        foreach (explode('.', substr($match[2], 1)) as $name) {
            if (!empty($name)) {
                $var = '\\Jade\\Compiler::getPropertyFromAnything(' .
                    static::addDollarIfNeeded($var) .
                    ', ' . var_export($name, true) . ')';
            }
        }

        return $var;
    }

    /**
     * Replace var paths in a string.
     *
     * @param $arg string
     * @param $regexp string
     *
     * @return string
     */
    protected static function convertVarPath($arg, $regexp = '/^%s|,%s/')
    {
        $pattern = '\s*(\\${0,2}' . static::VARNAME . ')((\.' . static::VARNAME . ')*)';

        return preg_replace_callback(
            str_replace('%s', $pattern, $regexp),
            array(get_class(), 'convertVarPathCallback'),
            $arg
        );
    }

    /**
     * Concat " = null" to initializations to simulate the JS "var foo;".
     *
     * @param &string reference of an argument containing an expression
     *
     * @throws \InvalidArgumentException
     */
    protected static function initArgToNull(&$arg)
    {
        $arg = static::addDollarIfNeeded(trim($arg));
        if (strpos($arg, '=') === false) {
            $arg .= ' = null';
        }
    }

    /**
     * Parse a value from its quoted string (or JSON) representation.
     *
     * @param $value string
     *
     * @return mixed
     */
    protected static function parseValue($value)
    {
        return json_decode(preg_replace("/'([^']*?)'/", '"$1"', $value));
    }

    /**
     * Decode a value (parse it except if it's null).
     *
     * @param $value string
     *
     * @return mixed
     */
    protected static function decodeValue($value)
    {
        $parsedValue = static::parseValue($value);

        return is_null($parsedValue) ? $value : $parsedValue;
    }

    /**
     * Decode each attribute in the given list.
     *
     * @param $attributes array
     *
     * @return array
     */
    protected static function decodeAttributes($attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_array($attribute)) {
                $attribute['value'] = $attribute['value'] === true ? $attribute['name'] : static::decodeValue($attribute['value']);
                continue;
            }

            $attribute = static::decodeValue($attribute);
        }

        return $attributes;
    }

    /**
     * Get filter by name.
     *
     * @param $name string
     *
     * @return callable
     */
    protected function getFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->filterAutoLoad);

        return $helper->getValidFilter($name);
    }

    /**
     * Return PHP code wich wrap the given value and escape it if $escaped is true.
     *
     * @param $escaped bool need to be escaped
     * @param $value mixed to be escaped if $escaped is true
     *
     * @return callable
     */
    protected function escapeIfNeeded($escaped, $value)
    {
        if ($escaped) {
            return $this->createCode(static::ESCAPED, $value, var_export($this->quote, true));
        }

        return $this->createCode(static::UNESCAPED, $value);
    }

    /**
     * Join with space if the value is an array, else return the input value
     * with no changes.
     *
     * @param $value array
     *
     * @return string|mixed
     */
    protected static function joinAny($value)
    {
        return is_array($value)
            ? implode(' ', $value)
            : $value;
    }
}
