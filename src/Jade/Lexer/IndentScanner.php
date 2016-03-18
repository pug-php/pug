<?php

namespace Jade\Lexer;

/**
 * Class Jade\Lexer\IndentScanner.
 */
abstract class IndentScanner extends InputHandler
{
    /**
     * Scan EOS from input & return it if found.
     *
     * @return object|null
     */
    protected function scanEOS()
    {
        if (!$this->length()) {
            if (count($this->indentStack)) {
                array_shift($this->indentStack);

                return $this->token('outdent');
            }

            return $this->token('eos');
        }
    }

    /**
     * @return object
     */
    protected function scanBlank()
    {
        if (preg_match('/^\n *\n/', $this->input, $matches)) {
            $this->consume(mb_substr($matches[0], 0, -1)); // do not cosume the last \r
            $this->lineno++;

            if ($this->pipeless) {
                return $this->token('text', '');
            }

            return $this->next();
        }
    }

    /**
     * @throws \Exception
     *
     * @return mixed|object
     */
    protected function scanIndent()
    {
        $matches = $this->getNextIndent();

        if ($matches !== null) {
            $indents = mb_strlen($matches[1]);

            $this->lineno++;
            $this->consume($matches[0]);
            $firstChar = substr($this->input, 0, 1);

            if ($this->length() && (' ' === $firstChar || "\t" === $firstChar)) {
                throw new \Exception('Invalid indentation, you can use tabs or spaces but not both');
            }

            return $this->getTokenFromIndent($firstChar, $indents);
        }
    }
}
