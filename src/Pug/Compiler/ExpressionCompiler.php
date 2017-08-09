<?php

namespace Pug\Compiler;

use JsPhpize\JsPhpize;
use Pug\Lexer\Scanner;
use Pug\Pug;

/**
 * Class Jade ExpressionCompiler.
 */
class ExpressionCompiler extends MixinVisitor
{
    /**
     * @var JsPhpize
     */
    protected $jsPhpize = null;

    public function getArgumentExpression($arg)
    {
        if ($this->getExpressionLanguage() === Jade::EXP_JS) {
            return $this->getPhpCodeFromJs([$arg]);
        }

        $arg = static::convertVarPath($arg);

        // add dollar if missing
        return preg_match('/^' . static::VARNAME . '(\s*,.+)?$/', $arg)
            ? static::addDollarIfNeeded($arg)
            : $arg;
    }

    protected function getExpressionLanguage()
    {
        $expressionLanguage = $this->getOption('expressionLanguage', 'auto');
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

    protected function getPhpCodeFromJs($arguments)
    {
        if (
            preg_match('/^\s*array\s*' . Scanner::PARENTHESES . '\s*$/i', $arguments[0]) ||
            preg_match('/^\(*isset\(\$/i', $arguments[0]) ||
            (
                preg_match('/^\s*array_merge\s*' . Scanner::PARENTHESES . '/i', $arguments[0]) &&
                preg_match('/\s*array\s*' . Scanner::PARENTHESES . '\s*/i', $arguments[0])
            )
        ) {
            return $arguments[0];
        }

        if ($this->jsPhpize === null) {
            $this->jsPhpize = new JsPhpize(array_merge_recursive([
                'catchDependencies' => true,
            ], $this->getOption('jsLanguage', [])));
        }

        return rtrim(trim(call_user_func([$this->jsPhpize, 'compileCode'], $arguments[0])), ';');
    }

    protected function jsToPhp($method, $arguments)
    {
        $code = $this->getPhpCodeFromJs($arguments);

        return in_array($method, ['handleCodePhp']) ? [$code] : $code;
    }

    public function phpizeExpression($method)
    {
        $arguments = array_slice(func_get_args(), 1);

        switch ($this->getExpressionLanguage()) {
            case Pug::EXP_PHP:
                return $arguments[0];
            case Pug::EXP_JS:
                return $this->jsToPhp($method, $arguments);
        }

        return call_user_func_array([get_called_class(), $method], $arguments);
    }
}
