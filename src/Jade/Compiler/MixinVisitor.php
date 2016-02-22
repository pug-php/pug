<?php

namespace Jade\Compiler;

use Jade\Nodes\Mixin;

abstract class MixinVisitor extends CodeVisitor
{
    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinCall(Mixin $mixin, $name, $blockName, $attributes)
    {
        $arguments = $mixin->arguments;
        $block = $mixin->block;
        $defaultAttributes = array();
        $newArrayKey = null;
        $containsOnlyArrays = true;
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
                if (count($tab) === 2) {
                    $defaultAttributes[] = var_export($tab[0], true) . ' => ' . $tab[1];
                    $arguments[$newArrayKey][$tab[0]] = static::decodeValue($tab[1]);
                } else {
                    $arguments[$newArrayKey][$tab[0]] = true;
                }
            } else {
                $containsOnlyArrays = false;
                $newArrayKey = null;
            }
        }
        $arguments = array_map(function ($argument) {
            if (is_array($argument)) {
                $argument = var_export($argument, true);
            }

            return $argument;
        }, $arguments);

        $defaultAttributes = implode(', ', $defaultAttributes);
        if (!count($attributes)) {
            $attributes = "(isset(\$attributes)) ? \$attributes : array($defaultAttributes)";
        } else {
            $_attr = array();
            foreach ($attributes as $data) {
                if ($data['value'] === 'null' || $data['value'] === 'undefined' || is_null($data['value'])) {
                    $_attr[$data['name']] = null;
                } elseif ($data['value'] === 'false' || is_bool($data['value'])) {
                    $_attr[$data['name']] = false;
                } else {
                    $value = trim($data['value']);
                    $_attr[$data['name']] = $data['escaped'] === true
                        ? htmlspecialchars($data['value'])
                        : $data['value'];
                }
            }

            $attributes = var_export($_attr, true);
            $mixinAttributes = var_export(static::decodeAttributes($mixin->attributes), true);
            $attributes = "array_merge(\\Jade\\Compiler::withMixinAttributes($attributes, $mixinAttributes), (isset(\$attributes)) ? \$attributes : array($defaultAttributes))";
        }

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
                $id = count($strings);
                $strings[] = $match[0];

                return 'stringToReplaceBy' . $id . 'ThCapture';
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

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinDeclaration(Mixin $mixin, $name, $blockName, $attributes)
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
        if ($this->allowMixinOverride) {
            $code = $this->createCode("{$name} = function (%s) { ", implode(',', $arguments));

            $this->buffer($code);
            $this->indents++;
            $this->visit($block);
            $this->indents--;
            $this->buffer($this->createCode('};'));
        } else {
            $code = $this->createCode("if(!function_exists('{$name}')) { function {$name}(%s) {", implode(',', $arguments));

            $this->buffer($code);
            $this->indents++;
            $this->visit($block);
            $this->indents--;
            $this->buffer($this->createCode('} }'));
        }
        if (is_null($previousVisitedMixin)) {
            unset($this->visitedMixin);
        } else {
            $this->visitedMixin = $previousVisitedMixin;
        }
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
        } else {
            $this->visitMixinDeclaration($mixin, $name, $blockName, $attributes);
        }
    }
}
