<?php

namespace Jade\Compiler;

/**
 * Class Jade CompilerConfig.
 * Definitions and low level settings.
 */
abstract class Indenter extends CompilerConfig
{
    /**
     * @var bool
     */
    protected $prettyprint = false;
    /**
     * @var int
     */
    protected $indents = 0;

    /**
     * @return string
     */
    protected function indent()
    {
        return $this->prettyprint ? str_repeat('  ', $this->indents) : '';
    }

    /**
     * @return string
     */
    protected function newline()
    {
        return $this->prettyprint ? "\n" : '';
    }

    /**
     * Disable or enable temporary the prettyprint setting then come back
     * to previous setting.
     *
     * @param bool prettyprint temporary setting
     * @param callable action to execute with the prettyprint setting
     *
     * @return mixed
     */
    protected function tempPrettyPrint($newSetting, $method)
    {
        $previousSetting = $this->prettyprint;
        $this->prettyprint = $newSetting;
        $result = call_user_func_array(array($this, $method), array_slice(func_get_args(), 2));
        $this->prettyprint = $previousSetting;

        return $result;
    }
}
