<?php

namespace Jade\Compiler;

abstract class AttributesCompiler extends CompilerFacade
{
    protected function getAttributeDisplayCode($key, $value, $valueCheck)
    {
        return is_null($valueCheck)
            ? ' ' . $key . '=' . $this->quote . $value . $this->quote
            : $this->createCode('if (\\Jade\\Compiler::isDisplayable($__value = %1$s)) { ', $valueCheck)
                . ' ' . $key . '=' . $this->quote . $value . $this->quote
                . $this->createCode('}');
    }

    protected function getBooleanAttributeDisplayCode($key)
    {
        return ' ' . $key . ($this->terse
            ? ''
            : '=' . $this->quote . $key . $this->quote
        );
    }

    protected function getAndAttributeCode($attr, &$classes, &$classesCheck)
    {
        $addClasses = '';
        if (count($classes) || count($classesCheck)) {
            foreach ($classes as &$value) {
                $value = var_export($value, true);
            }
            foreach ($classesCheck as $value) {
                $statements = $this->createStatements($value);
                $classes[] = $statements[0][0];
            }
            $addClasses = '$__attributes["class"] = ' .
                'implode(" ", array(' . implode(', ', $classes) . ')) . ' .
                '(empty($__attributes["class"]) ? "" : " " . $__attributes["class"]); ';
            $classes = array();
            $classesCheck = array();
        }
        $value = empty($attr['value']) ? 'attributes' : $attr['value'];
        $statements = $this->createStatements($value);

        return $this->createCode(
            '$__attributes = ' . $statements[0][0] . ';' .
            $addClasses .
            '\\Jade\\Compiler::displayAttributes($__attributes, ' . var_export($this->quote, true) . ');');
    }

    protected function getClassAttribute($value, &$classesCheck)
    {
        if ($this->keepNullAttributes) {
            return $this->createCode('echo (is_array($_a = %1$s)) ? implode(" ", $_a) : $_a', $value);
        }

        $statements = $this->createStatements($value);
        $classesCheck[] = '(is_array($_a = ' . $statements[0][0] . ') ? implode(" ", $_a) : $_a)';

        return 'null';
    }

    protected function getUnescapedValueCode($value, &$valueCheck)
    {
        if ($this->keepNullAttributes) {
            return $this->createCode(static::UNESCAPED, $value);
        }

        $valueCheck = $value;

        return $this->createCode(static::UNESCAPED, '$__value');
    }

    protected function getAttributeValue($key, $value, &$classesCheck, &$valueCheck)
    {
        if ($this->isConstant($value) || ($key != 'class' && $this->isArrayOfConstants($value))) {
            $value = trim($value, ' \'"');

            return $value === 'undefined' ? 'null' : $value;
        }

        $json = static::parseValue($value);

        if ($json !== null && is_array($json) && $key == 'class') {
            return implode(' ', $json);
        }

        if ($key == 'class') {
            return $this->getClassAttribute($value, $classesCheck);
        }

        return $this->getUnescapedValueCode($value, $valueCheck);
    }

    protected function compileAttributeValue($key, $value, $attr, $valueCheck)
    {
        if ($value == 'true' || $attr['value'] === true) {
            return $this->getBooleanAttributeDisplayCode($key);
        }

        if ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
            return $this->getAttributeDisplayCode($key, $value, $valueCheck);
        }

        return '';
    }

    protected function getAttributeCode($attr, &$classes, &$classesCheck)
    {
        $key = trim($attr['name']);

        if ($key === '&attributes') {
            return $this->getAndAttributeCode($attr, $classes, $classesCheck);
        }

        $valueCheck = null;
        $value = trim($attr['value']);

        $value = $this->getAttributeValue($key, $value, $classesCheck, $valueCheck);

        if ($key == 'class') {
            if ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                array_push($classes, $value);
            }

            return '';
        }

        return $this->compileAttributeValue($key, $value, $attr, $valueCheck);
    }

    protected function getClassesCode(&$classes, &$classesCheck)
    {
        if (count($classes)) {
            if (count($classesCheck)) {
                $classes[] = $this->createCode('echo implode(" ", array(' . implode(', ', $classesCheck) . '))');
            }

            return ' class=' . $this->quote . implode(' ', $classes) . $this->quote;
        }

        if (count($classesCheck)) {
            $item = $this->createCode('if("" !== ($__classes = implode(" ", array(' . implode(', ', $classesCheck) . ')))) {');
            $item .= ' class=' . $this->quote . $this->createCode('echo $__classes') . $this->quote;

            return $item . $this->createCode('}');
        }

        return '';
    }

    /**
     * @param $attributes
     */
    protected function compileAttributes($attributes)
    {
        $items = '';
        $classes = array();
        $classesCheck = array();

        foreach ($attributes as $attr) {
            $items .= $this->getAttributeCode($attr, $classes, $classesCheck);
        }

        $items .= $this->getClassesCode($classes, $classesCheck);

        $this->buffer(' ' . trim($items), false);
    }
}
