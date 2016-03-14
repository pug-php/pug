<?php

namespace Jade\Lexer;

/**
 * Class Jade\Lexer\InputHandler.
 */
abstract class InputHandler
{
    /**
     * @var string
     */
    public $input;

    /**
     * @var array
     */
    protected $deferred = array();

    /**
     * @var array
     */
    protected $indentStack = array();

    /**
     * @var array
     */
    protected $stash = array();

    /**
     * Set lexer input.
     *
     * @param string $input input string
     */
    public function setInput($input)
    {
        $this->input = preg_replace("/\r\n|\r/", "\n", $input);
        $this->lineno = 1;
        $this->deferred = array();
        $this->indentStack = array();
        $this->stash = array();
    }

    /**
     * @return int
     */
    public function length()
    {
        return mb_strlen($this->input);
    }

    /**
     * @param $code
     *
     * @return string
     */
    protected function normalizeCode($code)
    {
        // everzet's implementation used ':' at the end of the code line as in php's alternative syntax
        // this implementation tries to be compatible with both, js-jade and jade.php, so, remove the colon here
        return $code = (substr($code, -1) === ':' && substr($code, -2, 1) !== ':') ? substr($code, 0, -1) : $code;
    }

    protected function getNextIndent()
    {
        if (isset($this->identRE)) {
            return preg_match($this->identRE, $this->input, $matches) ? $matches : null;
        }

        $re = "/^\n(" . ($this->allowMixedIndent ? '[\t ]*' : '\t*') . ')/';
        $ok = preg_match($re, $this->input, $matches);

        if ($ok && mb_strlen($matches[1]) == 0) {
            $re = "/^\n( *)/";
            $ok = preg_match($re, $this->input, $matches);
        }

        if ($ok && mb_strlen($matches[1]) != 0) {
            $this->identRE = $re;
        }

        return $ok ? $matches : null;
    }
}
