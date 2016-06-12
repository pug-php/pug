<?php

namespace Jade\Compiler;

/**
 * Class Jade CompilerFacade.
 * Expose methods available from compiled jade tempaltes.
 */
abstract class CompilerFacade extends CompilerUtils
{
    protected static $mixinBlocks = array();

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
    public static function recordMixinBlock($name, $func = null)
    {
        if (!isset(static::$mixinBlocks[$name])) {
            static::$mixinBlocks[$name] = array();
        }
        array_push(static::$mixinBlocks[$name], $func);
    }

    /**
     * record a closure as a mixin block during execution jade template time.
     *
     * @param string  mixin name
     * @param string  mixin block treatment
     */
    public static function callMixinBlock($name, $attributes = array())
    {
        if (isset(static::$mixinBlocks[$name]) && is_array($mixinBlocks = static::$mixinBlocks[$name])) {
            $func = end($mixinBlocks);
            if (is_callable($func)) {
                $func($attributes);
            }
        }
    }

    /**
     * record a closure as a mixin block during execution jade template time
     * and propagate variables.
     *
     * @param string  mixin name
     * @param &array  variables handler propagated from parent scope
     * @param string  mixin block treatment
     */
    public static function callMixinBlockWithVars($name, &$varHandler, $attributes = array())
    {
        if (isset(static::$mixinBlocks[$name]) && is_array($mixinBlocks = static::$mixinBlocks[$name])) {
            $func = end($mixinBlocks);
            if (is_callable($func)) {
                $func($varHandler, $attributes);
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
        if (isset(static::$mixinBlocks[$name])) {
            array_pop(static::$mixinBlocks);
        }
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

    protected static function joinAny($value)
    {
        return is_array($value)
            ? implode(' ', $value)
            : $value;
    }

    public static function withMixinAttributes($attributes, $mixinAttributes)
    {
        foreach ($mixinAttributes as $attribute) {
            if ($attribute['name'] === 'class') {
                $value = static::joinAny($attribute['value']);
                $attributes['class'] = empty($attributes['class'])
                    ? $value
                    : static::joinAny($attributes['class']) . ' ' . $value;
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
                if ($key !== 'class' && $value !== false && $value !== 'null') {
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
