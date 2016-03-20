<?php

namespace Jade\Compiler;

use Jade\Nodes\Mixin;

abstract class MixinVisitor extends CodeVisitor
{
    protected function getMixinArgumentValueFromAssign($tab, &$defaultAttributes)
    {
        if (count($tab) === 2) {
            $defaultAttributes[] = var_export($tab[0], true) . ' => ' . $tab[1];

            return static::decodeValue($tab[1]);
        }

        return true;
    }

    protected function parseMixinArguments(&$arguments, &$containsOnlyArrays, &$defaultAttributes)
    {
        $newArrayKey = null;
        $arguments = is_null($arguments) ? array() : explode(',', $arguments);
        foreach ($arguments as $key => &$argument) {
            if (preg_match('`^\s*[a-zA-Z][a-zA-Z0-9:_-]*\s*=`', $argument)) {
                $tab = explode('=', trim($argument), 2);
                if (is_null($newArrayKey)) {
                    $newArrayKey = $key;
                    $argument = array();
                } else {
                    unset($arguments[$key]);
                }

                $arguments[$newArrayKey][$tab[0]] = $this->getMixinArgumentValueFromAssign($tab, $defaultAttributes);
                continue;
            }

            $containsOnlyArrays = false;
            $newArrayKey = null;
        }

        return array_map(function ($argument) {
            if (is_array($argument)) {
                $argument = var_export($argument, true);
            }

            return $argument;
        }, $arguments);
    }

    protected function parseMixinAttributes($attributes, $defaultAttributes, $mixinAttributes)
    {
        if (!count($attributes)) {
            return "(isset(\$attributes)) ? \$attributes : array($defaultAttributes)";
        }

        $parsedAttributes = array();
        foreach ($attributes as $data) {
            if ($data['value'] === 'null' || $data['value'] === 'undefined' || is_null($data['value'])) {
                $parsedAttributes[$data['name']] = null;
                continue;
            }

            if ($data['value'] === 'false' || is_bool($data['value'])) {
                $parsedAttributes[$data['name']] = false;
                continue;
            }

            $value = trim($data['value']);
            $parsedAttributes[$data['name']] = $data['escaped'] === true
                ? htmlspecialchars($value)
                : $value;
        }

        $attributes = var_export($parsedAttributes, true);
        $mixinAttributes = var_export(static::decodeAttributes($mixinAttributes), true);

        return "array_merge(\\Jade\\Compiler::withMixinAttributes($attributes, $mixinAttributes), (isset(\$attributes)) ? \$attributes : array($defaultAttributes))";
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinCall(Mixin $mixin, $name, $blockName, $attributes)
    {
        $arguments = $mixin->arguments;
        $block = $mixin->block;
        $defaultAttributes = array();
        $containsOnlyArrays = true;
        $arguments = $this->parseMixinArguments($mixin->arguments, $containsOnlyArrays, $defaultAttributes);

        $defaultAttributes = implode(', ', $defaultAttributes);
        $attributes = $this->parseMixinAttributes($attributes, $defaultAttributes, $mixin->attributes);

        if ($block) {
            $code = $this->createCode("\\Jade\\Compiler::recordMixinBlock($blockName, function (\$attributes) {");
            $this->buffer($code);
            $this->visit($block);
            $this->buffer($this->createCode('});'));
        }

        $strings = array();
        $arguments = preg_replace_callback(
            '#([\'"])(.*(?!<\\\\)(?:\\\\{2})*)\\1#U',
            function ($match) use (&$strings) {
                $nextIndex = count($strings);
                $strings[] = $match[0];

                return 'stringToReplaceBy' . $nextIndex . 'ThCapture';
            },
            $arguments
        );
        $arguments = array_map(
            function ($arg) use ($strings) {
                return preg_replace_callback(
                    '#stringToReplaceBy([0-9]+)ThCapture#',
                    function ($match) use ($strings) {
                        return $strings[intval($match[1])];
                    },
                    $arg
                );
            },
            $arguments
        );

        array_unshift($arguments, $attributes);
        $arguments = array_filter($arguments, 'strlen');
        $statements = $this->apply('createStatements', $arguments);

        $variables = array_pop($statements);
        if ($mixin->call && $containsOnlyArrays) {
            array_splice($variables, 1, 0, array('null'));
        }
        $variables = implode(', ', $variables);
        array_push($statements, $variables);

        $arguments = $statements;

        $code_format = str_repeat('%s;', count($arguments) - 1) . "{$name}(%s)";

        array_unshift($arguments, $code_format);

        $this->buffer($this->apply('createCode', $arguments));

        if ($block) {
            $code = $this->createCode("\\Jade\\Compiler::terminateMixinBlock($blockName);");
            $this->buffer($code);
        }
    }

    protected function visitMixinCodeAndBlock($name, $block, $arguments)
    {
        if ($this->allowMixinOverride) {
            $code = $this->createCode("{$name} = function (%s) { ", implode(',', $arguments));

            $this->buffer($code);
            $this->indents++;
            $this->visit($block);
            $this->indents--;
            $this->buffer($this->createCode('};'));

            return;
        }

        $code = $this->createCode("if(!function_exists('{$name}')) { function {$name}(%s) {", implode(',', $arguments));

        $this->buffer($code);
        $this->indents++;
        $this->visit($block);
        $this->indents--;
        $this->buffer($this->createCode('} }'));
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinDeclaration(Mixin $mixin, $name, $attributes)
    {
        $arguments = $mixin->arguments;
        $block = $mixin->block;
        $previousVisitedMixin = isset($this->visitedMixin) ? $this->visitedMixin : null;
        $this->visitedMixin = $mixin;
        if ($arguments === null || empty($arguments)) {
            $arguments = array();
        } elseif (!is_array($arguments)) {
            $arguments = array($arguments);
        }

        array_unshift($arguments, 'attributes');
        $arguments = implode(',', $arguments);
        $arguments = explode(',', $arguments);
        array_walk($arguments, array(get_class(), 'initArgToNull'));
        $this->visitMixinCodeAndBlock($name, $block, $arguments);

        if (is_null($previousVisitedMixin)) {
            unset($this->visitedMixin);

            return;
        }

        $this->visitedMixin = $previousVisitedMixin;
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixin(Mixin $mixin)
    {
        $name = strtr($mixin->name, '-', '_') . '_mixin';
        $blockName = var_export($mixin->name, true);
        if ($this->allowMixinOverride) {
            $name = '$GLOBALS[\'' . $name . '\']';
        }
        $attributes = static::decodeAttributes($mixin->attributes);

        if ($mixin->call) {
            $this->visitMixinCall($mixin, $name, $blockName, $attributes);

            return;
        }

        $this->visitMixinDeclaration($mixin, $name, $attributes);
    }
}
