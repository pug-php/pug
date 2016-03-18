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
            if ($this->keepNullAttributes) {
                return $this->createCode('echo (is_array($_a = %1$s)) ? implode(" ", $_a) : $_a', $value);
            }

            $statements = $this->createStatements($value);
            $classesCheck[] = '(is_array($_a = ' . $statements[0][0] . ') ? implode(" ", $_a) : $_a)';
            return 'null';
        }

        if ($this->keepNullAttributes) {
            return $this->createCode(static::UNESCAPED, $value);
        }

        $valueCheck = $value;
        return $this->createCode(static::UNESCAPED, '$__value');
    }

    /**
     * @param $attributes
     */
    protected function compileAttributes($attributes)
    {
        $items = array();
        $classes = array();
        $classesCheck = array();

        foreach ($attributes as $attr) {
            $key = trim($attr['name']);
            if ($key === '&attributes') {
                $items[] = $this->getAndAttributeCode($attr, $classes, $classesCheck);
                continue;
            }
            $valueCheck = null;
            $value = trim($attr['value']);

            $value = $this->getAttributeValue($key, $value, $classesCheck, $valueCheck);

            if ($key == 'class') {
                if ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                    array_push($classes, $value);
                }
            } elseif ($value == 'true' || $attr['value'] === true) {
                $items[] = $this->getBooleanAttributeDisplayCode($key);
            } elseif ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                $items[] = $this->getAttributeDisplayCode($key, $value, $valueCheck);
            }
        }

        if (count($classes)) {
            if (count($classesCheck)) {
                $classes[] = $this->createCode('echo implode(" ", array(' . implode(', ', $classesCheck) . '))');
            }
            $items[] = ' class=' . $this->quote . implode(' ', $classes) . $this->quote;
        } elseif (count($classesCheck)) {
            $item = $this->createCode('if("" !== ($__classes = implode(" ", array(' . implode(', ', $classesCheck) . ')))) {');
            $item .= ' class=' . $this->quote . $this->createCode('echo $__classes') . $this->quote;
            $items[] = $item . $this->createCode('}');
        }

        $this->buffer(' ' . trim(implode('', $items)), false);
    }
}
