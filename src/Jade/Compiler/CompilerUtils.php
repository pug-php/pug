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
        return CommonUtils::addDollarIfNeeded($call);
    }

    /**
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
        $parsedValue = static::parseValue($value);

        return is_null($parsedValue) ? $value : $parsedValue;
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
                $attribute['value'] = $attribute['value'] === true ? $attribute['name'] : static::decodeValue($attribute['value']);
                continue;
            }

            $attribute = static::decodeValue($attribute);
        }

        return $attributes;
    }

    /**
     * @param $name
     *
     * @return callable
     */
    protected function getFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->filterAutoLoad);

        return $helper->getValidFilter($name);
    }
}
