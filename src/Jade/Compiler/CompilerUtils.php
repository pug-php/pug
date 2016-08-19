<?php

namespace Jade\Compiler;

use Jade\Jade;
use JsPhpize\JsPhpize;

/**
 * Class Jade CompilerUtils.
 * Internal static methods of the compiler.
 */
abstract class CompilerUtils extends Indenter
{
    /**
     * Prepend "$" to the given input if it's a varname.
     *
     * @param string $call
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
     * @param string $val input value
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
     * @param array $match regex match
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
                $var = CommonUtils::getGetter($var, $name, false);
            }
        }

        return $var;
    }

    /**
     * Replace var paths in a string.
     *
     * @param string $arg
     * @param string $regexp
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
     * @param &string $arg reference of an argument containing an expression
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
     * @param string $value
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
     * Decode each attribute in the given list.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected static function decodeAttributes($attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_array($attribute)) {
                $attribute['value'] = is_bool($attribute['value']) ? $attribute['value'] : static::decodeValue($attribute['value']);
                continue;
            }

            $attribute = static::decodeValue($attribute);
        }

        return $attributes;
    }

    /**
     * Get filter by name.
     *
     * @param string $name
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
     * @param bool  $escaped need to be escaped
     * @param mixed $value   to be escaped if $escaped is true
     *
     * @return callable
     */
    protected function escapeIfNeeded($escaped, $value)
    {
        $value = rtrim($value, ';');

        if ($escaped) {
            return $this->createCode(static::ESCAPED, $value, var_export($this->quote, true));
        }

        return $this->createCode(static::UNESCAPED, $value);
    }

    protected function getExpressionLanguage()
    {
        $expressionLanguage = $this->getOption('expressionLanguage');
        if (is_string($expressionLanguage)) {
            $expressionLanguage = strtolower($expressionLanguage);
            if (substr($expressionLanguage, 0, 3) === 'php') {
                return Jade::EXP_PHP;
            }
            if (substr($expressionLanguage, 0, 2) === 'js' || substr($expressionLanguage, 0, 10) === 'javascript') {
                return Jade::EXP_JS;
            }
        }

        return Jade::EXP_AUTO;
    }

    protected function getPhpCodeFromJs($method, $arguments)
    {
        if (preg_match('/^\s*array\s*\([\s\S]*\)\s*$/i', $arguments[0])) {
            return $arguments[0];
        }

        static $jsPhpize = null;
        if ($jsPhpize === null) {
            $jsPhpize = new JsPhpize();
        }

        try {
            return rtrim(trim(call_user_func_array(array($jsPhpize, 'compileCode'), $arguments)), ';');
        } catch (\Exception $e) {
            throw new \Exception("Error Processing Expression\n" . implode("\n", $arguments) . "\n" . $e->getMessage(), 1, $e);
        }
    }

    protected function jsToPhp($method, $arguments)
    {
        $code = $this->getPhpCodeFromJs($method, $arguments);

        return in_array($method, array('handleCodePhp')) ? array($code) : $code;
    }

    protected function phpizeExpression($method)
    {
        $arguments = array_slice(func_get_args(), 1);

        switch ($this->getExpressionLanguage()) {
            case Jade::EXP_PHP:
                return $expression;
            case Jade::EXP_JS:
                return $this->jsToPhp($method, $arguments);
        }

        return call_user_func_array(array(get_class(), $method), $arguments);
    }

    /**
     * Join with space if the value is an array, else return the input value
     * with no changes.
     *
     * @param array $value
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
