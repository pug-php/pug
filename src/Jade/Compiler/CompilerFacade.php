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
     * Value treatment if it must not be escaped.
     *
     * @param string  input value
     *
     * @return string
     */
    public static function getUnescapedValue($val)
    {
        if (is_null($val) || $val === false || $val === '') {
            return '';
        }

        return is_array($val) || is_bool($val) || is_int($val) || is_float($val) ? json_encode($val) : strval($val);
    }

    /**
     * Value treatment if it must be escaped.
     *
     * @param string  input value
     *
     * @return string
     */
    public static function getEscapedValue($val, $quote)
    {
        $val = htmlspecialchars(static::getUnescapedValue($val), ENT_NOQUOTES);

        return str_replace($quote, $quote === '"' ? '&quot;' : '&apos;', $val);
    }

    /**
     * Convert style object to CSS string.
     *
     * @param mixed value to be computed into style.
     *
     * @return mixed
     */
    public static function styleValue($val)
    {
        if (is_array($val) && !is_string(key($val))) {
            $val = implode(';', $val);
        } elseif (is_array($val) || is_object($val)) {
            $style = array();
            foreach ($val as $key => $property) {
                $style[] = $key . ':' . $property;
            }

            $val = implode(';', $style);
        }

        return $val;
    }

    /**
     * Convert style object to CSS string and return PHP code to escape then display it.
     *
     * @param mixed value to be computed into style and escaped.
     *
     * @return string
     */
    public static function getEscapedStyle($val, $quote)
    {
        return static::getEscapedValue(static::styleValue($val), $quote);
    }

    /**
     * Convert style object to CSS string and return PHP code to display it.
     *
     * @param mixed value to be computed into style and stringified.
     *
     * @return string
     */
    public static function getUnescapedStyle($val)
    {
        return static::getUnescapedValue(static::styleValue($val));
    }

    /**
     * Record a closure as a mixin block during execution jade template time.
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
     * Record a closure as a mixin block during execution jade template time.
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
     * Record a closure as a mixin block during execution jade template time
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
     * End of the record of the mixin block.
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
     * Get property from object or entry from array.
     *
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
     * Merge given attributes such as tag attributes with mixin attributes.
     *
     * @param $attributes array
     * @param $mixinAttributes array
     *
     * @return array
     */
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
     * Display a list of attributes with the given quote character in HTML.
     *
     * @param $attributes array
     * @param $quote string
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
     * Return true if the given value can be display
     * (null or false should not be displayed in the output HTML).
     *
     * @param $value
     *
     * @return bool
     */
    public static function isDisplayable($value)
    {
        return !is_null($value) && $value !== false;
    }
}
