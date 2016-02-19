<?php

namespace Jade\Compiler;

/**
 * Class Jade CompilerFacade.
 * Expose methods available from compiled jade tempaltes.
 */
abstract class CompilerFacade extends CompilerUtils
{
    /**
     * value treatment if it must not be escaped.
     *
     * @param string  input value
     *
     * @return string
     */
    public static function strval($val)
    {
        return is_array($val) || is_null($val) || is_bool($val) || is_int($val) || is_float($val) ? json_encode($val) : strval($val);
    }

    /**
     * record a closure as a mixin block during execution jade template time.
     *
     * @param string  mixin name
     * @param string  mixin block treatment
     */
    public static function recordMixinBlock($name, $func = null, $terminate = false)
    {
        static $mixinBlocks = null;
        if (is_null($mixinBlocks)) {
            $mixinBlocks = array();
        }
        $isArray = isset($mixinBlocks[$name]) && is_array($mixinBlocks[$name]);
        if (is_null($func)) {
            if ($isArray) {
                if ($terminate) {
                    array_pop($mixinBlocks);
                } elseif (count($mixinBlocks[$name])) {
                    return $mixinBlocks[$name];
                }
            }
        } else {
            if (!$isArray) {
                $mixinBlocks[$name] = array();
            }
            array_push($mixinBlocks[$name], $func);
        }
    }

    /**
     * record a closure as a mixin block during execution jade template time.
     *
     * @param string  mixin name
     * @param string  mixin block treatment
     */
    public static function callMixinBlock($name, $attributes = array())
    {
        $mixinBlocks = static::recordMixinBlock($name);
        if (is_array($mixinBlocks)) {
            $func = end($mixinBlocks);
            if (is_callable($func)) {
                call_user_func($func, $attributes);
            }
        }
    }

    /**
     * end of the record a closure as a mixin block.
     *
     * @param string  mixin name
     */
    public static function terminateMixinBlock($name)
    {
        static::recordMixinBlock($name, null, true);
    }

    /**
     * @param $anything object|array
     * @param $key mixed key to retrive from the object or the array
     *
     * @return mixed
     */
    public static function getPropertyFromAnything($anything, $key)
    {
        $value = null;

        if (is_array($anything)) {
            $value = isset($anything[$key]) ? $anything[$key] : null;
        }

        if (is_object($anything)) {
            $value = isset($anything->$key) ? $anything->$key : null;
        }

        return $value;
    }

    /**
     * @param $match array regex match
     *
     * @return string
     */
    protected static function convertVarPathCallback($match)
    {
        if (empty($match[1])) {
            $var = $match[0];
        } else {
            $var = ($match[0] === ',' ? ',' : '') . $match[1];
            foreach (explode('.', substr($match[2], 1)) as $name) {
                if (!empty($name)) {
                    $var = '\\Jade\\Compiler::getPropertyFromAnything(' .
                        static::addDollarIfNeeded($var) .
                        ', ' . var_export($name, true) . ')';
                }
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

    public static function withMixinAttributes($attributes, $mixinAttributes)
    {
        foreach ($mixinAttributes as $attribute) {
            if ($attribute['name'] === 'class') {
                $attributes['class'] = empty($attributes['class'])
                    ? $attribute['value']
                    : $attributes['class'] . ' ' . $attribute['value'];
            }
        }
        if (isset($attributes['class'])) {
            $attributes['class'] = implode(' ', array_unique(explode(' ', $attributes['class'])));
        }

        return $attributes;
    }

    /**
     * @param $attributes
     */
    public static function displayAttributes($attributes, $quote)
    {
        if (is_array($attributes) || $attributes instanceof Traversable) {
            foreach ($attributes as $key => $value) {
                if ($value !== false && $value !== 'null') {
                    echo ' ' . $key . '=' . $quote . htmlspecialchars($value) . $quote;
                }
            }
        }
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function isDisplayable($value)
    {
        return !is_null($value) && $value !== false;
    }
}
