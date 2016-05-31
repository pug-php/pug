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

    protected function getValueStatement($statements)
    {
        return is_string($statements[0])
            ? $statements[0]
            : $statements[0][0];
    }

    protected function getAndAttributeCode($attr, &$classes, &$classesCheck)
    {
        $addClasses = '""';
        if (count($classes) || count($classesCheck)) {
            foreach ($classes as &$value) {
                $value = var_export($value, true);
            }
            foreach ($classesCheck as $value) {
                $statements = $this->createStatements($value);
                $classes[] = $statements[0][0];
            }
            $addClasses = '" " . implode(" ", array(' . implode(', ', $classes) . '))';
            $classes = array();
            $classesCheck = array();
        }
        $value = empty($attr['value']) ? 'attributes' : $attr['value'];
        $statements = $this->createStatements($value);

        return $this->createCode(
            '$__attributes = ' . $this->getValueStatement($statements) . ';' .
            'if (is_array($__attributes)) { ' .
                '$__attributes["class"] = trim(' .
                    '$__classes = (empty($__classes) ? "" : $__classes . " ") . ' .
                    '(isset($__attributes["class"]) ? (is_array($__attributes["class"]) ? implode(" ", $__attributes["class"]) : $__attributes["class"]) : "") . ' .
                    $addClasses .
                '); ' .
                'if (empty($__attributes["class"])) { ' .
                    'unset($__attributes["class"]); ' .
                '} ' .
            '} ' .
            '\\Jade\\Compiler::displayAttributes($__attributes, ' . var_export($this->quote, true) . ');');
    }

    protected function getClassAttribute($value, &$classesCheck)
    {
        $statements = $this->createStatements($value);
        $classesCheck[] = '(is_array($_a = ' . $statements[0][0] . ') ? implode(" ", $_a) : $_a)';

        return $this->keepNullAttributes ? '' : 'null';
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

        if ($key === 'class') {
            if ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                array_push($classes, $value);
            }

            return '';
        }

        return $this->compileAttributeValue($key, $value, $attr, $valueCheck);
    }

    protected function getClassesCode(&$classes, &$classesCheck)
    {
        return trim($this->createCode(
            '$__classes = implode(" ", ' .
                'array_unique(explode(" ", (empty($__classes) ? "" : $__classes) . ' .
                    var_export(implode(' ', $classes), true) . ' . ' .
                    'implode(" ", array(' . implode(', ', $classesCheck) . ')) ' .
                ')) ' .
            ');'
        ));
    }

    protected function getClassesDisplayCode()
    {
        return trim($this->createCode(
            'if (!empty($__classes)) { ' .
                '?> class=' . $this->quote . '<?php echo $__classes; ?>' . $this->quote . '<?php ' .
            '} ' .
            'unset($__classes); '
        ));
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

        $this->buffer($items, false);
    }
}
