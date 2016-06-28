<?php

namespace Jade\Lexer;

/**
 * Class Jade\Lexer\Scanner.
 */
abstract class Scanner extends MixinScanner
{
    /**
     *  Helper to create tokens.
     */
    protected function scan($regex, $type, $captureIndex = 1)
    {
        if (preg_match($regex, $this->input, $matches)) {
            $this->consume($matches[0]);

            return $this->token($type, isset($matches[$captureIndex]) && mb_strlen($matches[$captureIndex]) > 0 ? $matches[$captureIndex] : '');
        }
    }

    /**
     * Scan comment from input & return it if found.
     *
     * @return object|null
     */
    protected function scanComment()
    {
        $indent = count($this->indentStack) ? $this->indentStack[0] : 0;
        if (preg_match('/^ *\/\/(-)?([^\n]*(\n+[ \t]{' . ($indent + 1) . ',}[^\n]*)*)/', $this->input, $matches)) {
            $this->consume($matches[0]);
            $value = isset($matches[2]) ? $matches[2] : '';
            if (isset($matches[3])) {
                $value .= "\n";
            }
            $token = $this->token('comment', $value);
            $token->buffer = '-' !== $matches[1];

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanInterpolation()
    {
        return $this->scan('/^#{(.*?)}/', 'interpolation');
    }

    /**
     * @return object
     */
    protected function scanTag()
    {
        if (preg_match('/^(\w[-:\w]*)(\/?)/', $this->input, $matches)) {
            $this->consume($matches[0]);
            $name = $matches[1];

            if (':' === mb_substr($name, -1) && ':' !== mb_substr($name, -2, 1)) {
                $name = mb_substr($name, 0, -1);
                $this->defer($this->token(':'));

                while (' ' === mb_substr($this->input, 0, 1)) {
                    $this->consume(' ');
                }
            }

            $token = $this->token('tag', $name);
            $token->selfClosing = ($matches[2] === '/');

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanFilter()
    {
        return $this->scan('/^(?<!:):(?!:)(\w+(?:-\w+)*)/', 'filter');
    }

    /**
     * @return object
     */
    protected function scanDoctype()
    {
        return $this->scan('/^(?:!!!|doctype) *([^\n]+)?/', 'doctype');
    }

    /**
     * @return object
     */
    protected function scanId()
    {
        return $this->scan('/^#([\w-]+)/', 'id');
    }

    /**
     * @return object
     */
    protected function scanClassName()
    {
        // http://www.w3.org/TR/CSS21/grammar.html#scanner
        //
        // ident:
        //      -?{nmstart}{nmchar}*
        // nmstart:
        //      [_a-z]|{nonascii}|{escape}
        // nonascii:
        //      [\240-\377]
        // escape:
        //      {unicode}|\\[^\r\n\f0-9a-f]
        // unicode:
        //      \\{h}{1,6}(\r\n|[ \t\r\n\f])?
        // nmchar:
        //      [_a-z0-9-]|{nonascii}|{escape}
        //
        // /^(-?(?!=[0-9-])(?:[_a-z0-9-]|[\240-\377]|\\{h}{1,6}(?:\r\n|[ \t\r\n\f])?|\\[^\r\n\f0-9a-f])+)/
        return $this->scan('/^\.([\w-]+)/', 'class');
    }

    /**
     * @return object
     */
    protected function scanText()
    {
        return $this->scan('/^(?:\| ?| ?)?([^\n]+)/', 'text');
    }

    /**
     * @return object
     */
    protected function scanAssignment()
    {
        if (preg_match('/^(\$?\w+) += *([^;\n]+|\'[^\']+\'|"[^"]+")( *;? *)/', $this->input, $matches)) {
            $this->consume($matches[0]);

            return $this->token('code', (substr($matches[1], 0, 1) === '$' ? '' : '$') . $matches[1] . '=' . $matches[2]);
        }
    }

    /**
     * @return object
     */
    protected function scanConditional()
    {
        if (preg_match('/^(if|unless|else if|elseif|else|while)\b([^\n]*)/', $this->input, $matches)) {
            $this->consume($matches[0]);

            /*switch ($matches[1]) {
                case 'if': $code = 'if (' . $matches[2] . '):'; break;
                case 'unless': $code = 'if (!(' . $matches[2] . ')):'; break;
                case 'else if': $code = 'elseif (' . $matches[2] . '):'; break;
                case 'else': $code = 'else (' . $matches[2] . '):'; break;
            }*/
            $code = $this->normalizeCode($matches[0]);
            $token = $this->token('code', $code);
            $token->buffer = false;

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanEach()
    {
        if (preg_match('/^(?:- *)?(?:each|for) +(\w+)(?: *, *(\w+))? +in *([^\n]+)/', $this->input, $matches)) {
            $this->consume($matches[0]);

            $token = $this->token('each', $matches[1]);
            $token->key = $matches[2];
            $token->code = $this->normalizeCode($matches[3]);

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanCode()
    {
        if (preg_match('/^(!?=|-)([^\n]+)/', $this->input, $matches)) {
            $this->consume($matches[0]);
            $flags = $matches[1];
            $code = $this->normalizeCode($matches[2]);

            $token = $this->token('code', $code);
            $token->escape = $flags[0] === '=';
            $token->buffer = '=' === $flags[0] || (isset($flags[1]) && '=' === $flags[1]);

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanAttributes()
    {
        if (substr($this->input, 0, 1) === '(') {
            // cant use ^ anchor in the regex because the pattern is recursive
            // but this restriction is asserted by the if above
            //$this->input = preg_replace('/([a-zA-Z0-9\'"\\]\\}\\)])([\t ]+[a-zA-Z])/', '$1,$2', $this->input);
            if (!preg_match('/\((?:[^()]++|(?R))*+\)/', $this->input, $matches)) {
                throw new \Exception('Unable to find attributes closing parenthesis.', 1);
            }
            $this->consume($matches[0]);

            //$str = preg_replace('/()([a-zA-Z0-9_\\x7f-\\xff\\)\\]\\}"\'])(\s+[a-zA-Z_])/', '$1,$2', $str);

            $token = $this->token('attributes');
            $token->attributes = array();
            $token->escaped = array();
            $token->selfClosing = false;

            $parser = new Attributes($token);
            $parser->parseWith(mb_substr($matches[0], 1, mb_strlen($matches[0]) - 2));

            if ($this->length() && '/' == $this->input[0]) {
                $this->consume(1);
                $token->selfClosing = true;
            }

            return $token;
        }
    }

    /**
     * @return object
     */
    protected function scanPipelessText()
    {
        if ($this->pipeless && "\n" !== substr($this->input, 0, 1)) {
            $pos = mb_strpos($this->input, "\n");

            if ($pos === false) {
                $pos = $this->length();
            }

            $str = mb_substr($this->input, 0, $pos); // do not include the \n char

            $this->consume($str);

            return $this->token('text', ltrim($str));
        }
    }

    /**
     * @return object
     */
    protected function scanColon()
    {
        return $this->scan('/^:(?!:) */', ':');
    }

    /**
     * @return object
     */
    protected function scanAndAttributes()
    {
        return $this->scan('/^&attributes(\(((?>[^()]+|(?1))*)\))/', '&attributes', 2);
    }
}
