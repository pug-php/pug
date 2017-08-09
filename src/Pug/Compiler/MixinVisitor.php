<?php

namespace Pug\Compiler;

use Pug\Nodes\Mixin;

abstract class MixinVisitor extends MixinVisitorUtils
{
    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinCall(Mixin $mixin, $name, $blockName, $attributes)
    {
        $arguments = $mixin->arguments;
        $block = $mixin->block;
        $defaultAttributes = [];
        $containsOnlyArrays = true;
        $arguments = $this->parseMixinArguments($mixin->arguments, $containsOnlyArrays, $defaultAttributes);

        $defaultAttributes = implode(', ', $defaultAttributes);
        $attributes = $this->parseMixinAttributes($attributes, $defaultAttributes, $mixin->attributes);

        if ($block) {
            $this->renderClosureOpenning("\\Pug\\Compiler::recordMixinBlock($blockName, ", 'attributes');
            $this->visit($block);
            $this->renderClosureClosing('});');
        }

        $strings = [];
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
            array_splice($variables, 1, 0, ['null']);
        }
        $variables = implode(', ', $variables);
        array_push($statements, $variables);

        $arguments = $statements;

        $paramsPrefix = '';
        if (!$this->restrictedScope) {
            $this->buffer($this->createCode('$__varHandler = get_defined_vars();'));
            $paramsPrefix = '$__varHandler, ';
        }
        $codeFormat = str_repeat('%s;', count($arguments) - 1) . "{$name}({$paramsPrefix}%s)";

        array_unshift($arguments, $codeFormat);

        $this->buffer($this->apply('createCode', $arguments));
        if (!$this->restrictedScope) {
            $this->buffer(
                $this->createCode(
                    'extract(array_diff_key($__varHandler, array(\'__varHandler\' => 1, \'attributes\' => 1)));'
                )
            );
        }

        if ($block) {
            $code = $this->createCode("\\Pug\\Compiler::terminateMixinBlock($blockName);");
            $this->buffer($code);
        }
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinDeclaration(Mixin $mixin, $name)
    {
        $arguments = $mixin->arguments;
        $block = $mixin->block;
        $previousVisitedMixin = isset($this->visitedMixin) ? $this->visitedMixin : null;
        $this->visitedMixin = $mixin;
        if ($arguments === null || empty($arguments)) {
            $arguments = [];
        } elseif (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        array_unshift($arguments, 'attributes');
        $arguments = $this->splitArguments(implode(',', $arguments));
        array_walk($arguments, [get_class(), 'initArgToNull']);
        $this->visitMixinCodeAndBlock($name, $block, $arguments);

        if (is_null($previousVisitedMixin)) {
            unset($this->visitedMixin);

            return;
        }

        $this->visitedMixin = $previousVisitedMixin;
    }

    /**
     * @param Nodes\Mixin $mixin
     *
     * @return array name and block name
     */
    protected function getMixinName(Mixin $mixin)
    {
        if (preg_match('/^#\\{(.+)\\}$/', $mixin->name, $match)) {
            if ($this->allowMixinOverride) {
                $blockName = '(' . $this->phpizeExpression('addDollarIfNeeded', $match[1]) . ') . \'_mixin\'';

                return ['$GLOBALS[' . $blockName . ']', $blockName];
            }

            $name = '$__callee = (' . $this->phpizeExpression('addDollarIfNeeded', $match[1]) . ') . \'_mixin\'; $__callee';

            return [$name, $name];
        }

        $name = strtr($mixin->name, '-', '_') . '_mixin';

        if ($this->allowMixinOverride) {
            $name = '$GLOBALS[\'' . $name . '\']';
        }

        return [$name, var_export($mixin->name, true)];
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixin(Mixin $mixin)
    {
        list($name, $blockName) = $this->getMixinName($mixin);
        $attributes = static::decodeAttributes($mixin->attributes);

        if ($mixin->call) {
            $this->visitMixinCall($mixin, $name, $blockName, $attributes);

            return;
        }

        $this->visitMixinDeclaration($mixin, $name);
    }
}
