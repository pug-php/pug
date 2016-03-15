<?php

namespace Jade;

use Jade\Lexer\Scanner;

/**
 * Class Jade\Lexer.
 */
class Lexer extends Scanner
{
    /**
     * @var int
     */
    public $lineno = 1;

    /**
     * @var bool
     */
    public $pipeless;

    /**
     * @var bool
     */
    public $allowMixedIndent;

    /**
     * @var int
     */
    public $lastTagIndent = -2;

    /**
     * @param $input
     */
    public function __construct($input, array $options = array())
    {
        $this->allowMixedIndent = isset($options['allowMixedIndent']) && $options['allowMixedIndent'];
        $this->setInput($input);
    }

    /**
     * Construct token with specified parameters.
     *
     * @param string $type  token type
     * @param string $value token value
     *
     * @return object new token object
     */
    public function token($type, $value = null)
    {
        return (object) array(
            'type' => $type,
            'line'   => $this->lineno,
            'value'  => $value,
        );
    }

    /**
     * Consume input.
     *
     * @param string $bytes utf8 string of input to consume
     */
    protected function consume($bytes)
    {
        $this->input = mb_substr($this->input, mb_strlen($bytes));
    }

    /**
     * Defer token.
     *
     * @param \stdClass $token token to defer
     */
    public function defer(\stdClass $token)
    {
        $this->deferred[] = $token;
    }

    /**
     * Lookahead token 'n'.
     *
     * @param int $number number of tokens to predict
     *
     * @return object predicted token
     */
    public function lookahead($number = 1)
    {
        $fetch = $number - count($this->stash);

        while ($fetch-- > 0) {
            $this->stash[] = $this->next();
        }

        return $this->stash[--$number];
    }

    /**
     * Return stashed token.
     *
     * @return object|bool token if has stashed, false otherways
     */
    protected function getStashed()
    {
        return count($this->stash) ? array_shift($this->stash) : null;
    }

    /**
     * Return deferred token.
     *
     * @return object|bool token if has deferred, false otherways
     */
    protected function deferred()
    {
        return count($this->deferred) ? array_shift($this->deferred) : null;
    }

    /**
     * Return next token or previously stashed one.
     *
     * @return object
     */
    public function advance()
    {
        $token = $this->getStashed()
        or $token = $this->next();

        return $token;
    }

    /**
     * Return next token.
     *
     * @return object
     */
    protected function next()
    {
        return $this->nextToken();
    }

    /**
     * @return bool|mixed|null|object|void
     */
    public function nextToken()
    {
        $r = $this->deferred()
        or $r = $this->scanBlank()
        or $r = $this->scanEOS()
        or $r = $this->scanPipelessText()
        or $r = $this->scanYield()
        or $r = $this->scanDoctype()
        or $r = $this->scanInterpolation()
        or $r = $this->scanCase()
        or $r = $this->scanWhen()
        or $r = $this->scanDefault()
        or $r = $this->scanExtends()
        or $r = $this->scanAppend()
        or $r = $this->scanPrepend()
        or $r = $this->scanBlock()
        or $r = $this->scanInclude()
        or $r = $this->scanMixin()
        or $r = $this->scanCall()
        or $r = $this->scanConditional()
        or $r = $this->scanEach()
        or $r = $this->scanAssignment()
        or $r = $this->scanTag()
        or $r = $this->scanFilter()
        or $r = $this->scanCode()
        or $r = $this->scanId()
        or $r = $this->scanClassName()
        or $r = $this->scanAttributes()
        or $r = $this->scanIndent()
        or $r = $this->scanComment()
        or $r = $this->scanColon()
        or $r = $this->scanAndAttributes()
        or $r = $this->scanText();

        return $r;
    }
}
