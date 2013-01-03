<?php

namespace Jade;

class Lexer {

    public $lineno = 1;
    public $pipeless;
    public $input;

    protected $deferred		= array();
    protected $indentStack	= 0;
    protected $stash		= array();

    public function __construct($input) {
        $this->setInput($input);
    }

    /**
     * Set lexer input.
     *
     * @param   string  $input  input string
     */
    public function setInput($input) {
        $this->input		= preg_replace("/\r\n|\r/", "\n", $input);
        $this->lineno       = 1;
        $this->deferred		= array();
        $this->indentStack  = array();
        $this->stash        = array();
    }

    /**
     * Construct token with specified parameters.
     *
     * @param   string  $type   token type
     * @param   string  $value  token value
     *
     * @return  Object          new token object
     */
    public function token($type, $value = null) {
        return (Object) array(
            'type'  => $type
            , 'line'  => $this->lineno
            , 'value' => $value
        );
    }

    function length() {
        return mb_strlen($this->input);
    }

    /**
     * Consume input.
     *
     * @param   string $bytes utf8 string of input to consume
     */
    protected function consume($bytes) {
        $this->input = mb_substr($this->input, mb_strlen($bytes));
    }

    protected function normalizeCode($code) {
        // everzet's implementation used ':' at the end of the code line as in php's alternative syntax
        // this implementation tries to be compatible with both, js-jade and jade.php, so, remove the colon here
        return $code = (substr($code,-1) == ':') ? substr($code,0,-1) : $code;
    }

    /**
     *  Helper to create tokens
     */
    protected function scan($regex, $type) {

        if( preg_match($regex, $this->input, $matches) ){
            $this->consume($matches[0]);
            return $this->token($type, isset($matches[1]) && mb_strlen($matches[1]) > 0 ? $matches[1] : '' );
        }
    }

    /**
     * Defer token.
     *
     * @param   Object   $token  token to defer
     */
    public function defer(\stdClass $token) {
        $this->deferred[] = $token;
    }

    /**
     * Lookahead token 'n'.
     *
     * @param   integer     $number number of tokens to predict
     *
     * @return  Object              predicted token
     */
    public function lookahead($number = 1) {
        $fetch = $number - count($this->stash);

        while ( $fetch-- > 0 ) {
            $this->stash[] = $this->next();
        }

        return $this->stash[--$number];
    }

    /**
     * Return stashed token.
     *
     * @return  Object|boolean   token if has stashed, false otherways
     */
    protected function getStashed() {
        return count($this->stash) ? array_shift($this->stash) : null;
    }

    /**
     * Return deferred token.
     *
     * @return  Object|boolean   token if has deferred, false otherways
     */
    protected function deferred() {
        return count($this->deferred) ? array_shift($this->deferred) : null;
    }

    /**
     * Return next token or previously stashed one.
     *
     * @return  Object
     */
    public function advance() {
        $token = $this->getStashed()
            or $token = $this->next();

        return $token;
    }

    /**
     * Return next token.
     *
     * @return  Object
     */
    protected function next() {
        return $this->nextToken();
    }

    /**
     * Scan EOS from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanEOS() {
        if (!$this->length()) {

            if (count($this->indentStack)) {
                array_shift($this->indentStack);
                return $this->token('outdent');
            }
            return $this->token('eos');
        }
    }

    protected function scanBlank() {

        if( preg_match('/^\n *\n/', $this->input, $matches) ){
            $this->consume(mb_substr($matches[0],0,-1)); // do not cosume the last \r
            $this->lineno++;

            if ($this->pipeless) {
                return $this->token('text','');
            }

            return $this->next();
        }
    }

    /**
     * Scan comment from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanComment() {

        if ( preg_match('/^ *\/\/(-)?([^\n]*)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('comment', isset($matches[2]) ? $matches[2] : '');
            $token->buffer = '-' !== $matches[1];

            return $token;
        }
    }

    protected function scanInterpolation() {
        return $this->scan('/^#{(.*?)}/', 'interpolation');
    }

    protected function scanTag() {

        if ( preg_match('/^(\w[-:\w]*)(\/?)/',$this->input,$matches) ) {
            $this->consume($matches[0]);
            $name = $matches[1];

            if ( ':' == mb_substr($name,-1) ) {

                $name = mb_substr($name,0,-1);
                $token = $this->token('tag',$name);
                $this->defer($this->token(':'));

                while ( ' ' == mb_substr($this->input,0,1) ) $this->consume(mb_substr($this->input,0,1));
            } else {
                $token = $this->token('tag', $name);
            }

            $token->selfClosing = ($matches[2] == '/') ? true: false;

            return $token;
        }
    }

    protected function scanFilter() {
        return $this->scan('/^:(\w+)/', 'filter');
    }

    protected function scanDoctype() {
        return $this->scan('/^(?:!!!|doctype) *([^\n]+)?/', 'doctype');
    }

    protected function scanId() {
        return $this->scan('/^#([\w-]+)/','id');
    }

    protected function scanClassName() {
        // http://www.w3.org/TR/CSS21/grammar.html#scanner
        //
        // ident:
        //		-?{nmstart}{nmchar}*
        // nmstart:
        //		[_a-z]|{nonascii}|{escape}
        // nonascii:
        //		[\240-\377]
        // escape:
        //		{unicode}|\\[^\r\n\f0-9a-f]
        // unicode:
        //		\\{h}{1,6}(\r\n|[ \t\r\n\f])?
        // nmchar:
        //		[_a-z0-9-]|{nonascii}|{escape}
        //
        // /^(-?(?!=[0-9-])(?:[_a-z0-9-]|[\240-\377]|\\{h}{1,6}(?:\r\n|[ \t\r\n\f])?|\\[^\r\n\f0-9a-f])+)/
        return $this->scan('/^[.]([\w-]+)/','class');
    }

    protected function scanText() {
        return $this->scan('/^(?:\| ?| ?)?([^\n]+)/','text');
    }

    protected function scanExtends() {
        return $this->scan('/^extends? +([^\n]+)/','extends');
    }

    protected function scanPrepend() {

        if ( preg_match('/^prepend +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[1]);
            $token->mode = 'prepend';
            return $token;
        }
    }

    protected function scanAppend() {

        if( preg_match('/^append +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[1]);
            $token->mode = 'append';
            return $token;
        }
    }

    protected function scanBlock() {

        if( preg_match("/^block\b *(?:(prepend|append) +)?([^\n]*)/", $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[2]);
            $token->mode = (mb_strlen($matches[1]) == 0) ? 'replace' : $matches[1];
            return $token;
        }
    }

    protected function scanYield() {
        return $this->scan('/^yield */', 'yield');
    }

    protected function scanInclude() {
        return $this->scan('/^include +([^\n]+)/', 'include');
    }

    protected function scanCase() {
        return $this->scan('/^case +([^\n]+)/', 'case');
    }

    protected function scanWhen() {
        return $this->scan('/^when +([^:\n]+)/', 'when');
    }

    protected function scanDefault() {
        return $this->scan('/^default */', 'default');
    }

    protected function scanAssignment() {
        if ( preg_match('/^(\w+) += *(\'[^\']+\'|"[^"]+"|[^;\n]+)( *;? *)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            return $this->token('code', $matches[1] . ' = ' . $matches[2]);
        }
    }

    protected function scanCall() {
        if ( preg_match('/^\+([-\w]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('call', $matches[1]);

            # check for arguments
            if ( preg_match( '/^ *\((.*?)\)/', $this->input, $matches_arguments) ) {
                $this->consume($matches_arguments[0]);
                $token->arguments = $matches_arguments[1];
            }

            return $token;
        }
    }

    protected function scanMixin() {
        if ( preg_match('/^mixin +([-\w]+)(?: *\((.*)\))?/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('mixin', $matches[1]);
            $token->arguments = isset($matches[2]) ? $matches[2] : null;
            return $token;
        }
    }

    protected function scanConditional() {
        if ( preg_match('/^(if|unless|else if|else)\b([^\n]*)/', $this->input, $matches) ) {
            $this->consume($matches[0]);

            /*switch ($matches[1]) {
                case 'if': $code = 'if (' . $matches[2] . '):'; break;
                case 'unless': $code = 'if (!(' . $matches[2] . ')):'; break;
                case 'else if': $code = 'elseif (' . $matches[2] . '):'; break;
                case 'else': $code = 'else (' . $matches[2] . '):'; break;
            }*/
            $code   = $this->normalizeCode($matches[0]);
            $token  = $this->token('code', $code);
            $token->buffer = false;
            return $token;
        }
    }

    protected function scanWhile() {
        if ( preg_match('/^while +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $this->token('code', 'while (' . $matches[1] . '):');
        }
    }

    protected function scanEach() {
        if ( preg_match('/^(?:- *)?(?:each|for) +(\w+)(?: *, *(\w+))? +in *([^\n]+)/', $this->input, $matches) ) {

            $this->consume($matches[0]);

            $token = $this->token('each', $matches[1]);
            $token->key = $matches[2];
            $token->code = $this->normalizeCode($matches[3]);

            return $token;
        }
    }

    protected function scanCode() {
        if ( preg_match('/^(!?=|-)([^\n]+)/', $this->input, $matches) ) {

            $this->consume($matches[0]);
            $flags  = $matches[1];
            $code   = $this->normalizeCode($matches[2]);

            $token = $this->token('code', $code);
            $token->escape = $flags[0] === '=';
            $token->buffer = '=' === $flags[0] || (isset($flags[1]) && '=' === $flags[1]);

            return $token;
        }
    }

    protected function scanAttributes() {
        if ( $this->input[0] === '(' ) {
            // cant use ^ anchor in the regex because the pattern is recursive
            // but this restriction is asserted by the if above
            preg_match('/\((?:[^()]++|(?R))*+\)/', $this->input, $matches);
            $this->consume($matches[0]);

            $str = mb_substr($matches[0],1,mb_strlen($matches[0])-2);

            $token = $this->token('attributes');
            $token->attributes	= array();
            $token->escaped		= array();
            $token->selfClosing	= false;

            $key	= '';
            $val	= '';
            $quote	= '';
            $states	= array('key');
            $escapedAttribute	= '';
            $previousChar		= '';

            $state = function() use(&$states) {
                return $states[count($states)-1];
            };

            $interpolate = function($attr) use (&$quote) {
                // the global flag is turned on by default
                // TODO: check the +, maybe it is better to use . here
                return str_replace('\\#{', '#{', preg_replace('/(?<!\\\\)#{([^}]+)}/', $quote . ' + $1 + ' . $quote, $attr));
            };

            $parse = function($char) use (&$key, &$val, &$quote, &$states, &$token, &$escapedAttribute, &$previousChar, $state, $interpolate) {
                switch ($char) {
                case ',':
                case "\n":
                    switch ($state()) {
                    case 'expr':
                    case 'array':
                    case 'string':
                    case 'object':
                        $val = $val . $char;
                        break;

                    default:
                        array_push($states, 'key');
                        $val = trim($val);
                        $key = trim($key);

                        if (empty($key)) return;

                        $key = preg_replace(
                            array('/^[\'\"]|[\'\"]$/','/\!/')
                            ,''
                            ,$key
                        );
                        $token->escaped[$key]	= $escapedAttribute;
                        $token->attributes[$key]= ('' == $val) ? true : $interpolate($val);

                        $key = '';
                        $val = '';
                    }
                    break;

                    case '=':
                        switch ($state()) {
                        case 'key char':
                            $key = $key . $char;
                            break;

                        case 'val':
                        case 'expr':
                        case 'array':
                        case 'string':
                        case 'object':
                            $val = $val . $char;
                            break;

                        default:
                            $escapedAttribute = '!' != $previousChar;
                            array_push($states,'val');
                        }
                        break;

                        case '(':
                            if ($state() == 'val' || $state() == 'expr') {
                                array_push($states,'expr');
                            }
                            $val = $val . $char;
                            break;

                        case ')':
                            if ($state() == 'val' || $state() == 'expr') {
                                array_pop($states);
                            }
                            $val = $val . $char;
                            break;

                        case '{':
                            if ($state() == 'val') {
                                array_push($states, 'object');
                            }
                            $val = $val . $char;
                            break;

                        case '}':
                            if ($state() == 'object') {
                                array_pop($states);
                            }
                            $val = $val . $char;
                            break;

                        case '[':
                            if ($state() == 'val') {
                                array_push($states, 'array');
                            }
                            $val = $val . $char;
                            break;

                        case ']':
                            if ($state() == 'array') {
                                array_pop($states);
                            }
                            $val = $val . $char;
                            break;

                        case '"':
                        case "'":
                            switch ($state()) {
                            case 'key':
                                array_push($states, 'key char');
                                break;

                            case 'key char':
                                array_pop($states);
                                break;

                            case 'string':
                                if ($char == $quote) {
                                    array_pop($states);
                                }
                                $val = $val . $char;
                                break;

                            default:
                                array_push($states, 'string');
                                $val = $val . $char;
                                $quote = $char;
                                break;
                            }
                            break;

                            case '':
                                break;

                            default:
                                switch ($state()) {
                                case 'key':
                                case 'key char':
                                    $key = $key .$char;
                                    break;

                                default:
                                    $val = $val . $char;
                                    break;
                                }
                }
                $previousChar = $char;
            };

            for ($i=0;$i<mb_strlen($str);$i++) {
                $parse(mb_substr($str, $i, 1));
            }

            $parse(',');

            if ($this->length() && '/' == $this->input[0]) {
                $this->consume(1);
                $token->selfClosing = true;
            }

            return $token;
        }
    }

    protected function scanIndent() {

        if (isset($this->identRE)) {
            $ok = preg_match($this->identRE, $this->input, $matches);
        }else{
            $re = "/^\n(\t*) */";
            $ok = preg_match($re, $this->input, $matches);

            if ($ok && mb_strlen($matches[1]) == 0) {
                $re = "/^\n( *)/";
                $ok = preg_match($re, $this->input, $matches);
            }

            if ($ok && mb_strlen($matches[1]) != 0) {
                $this->identRE = $re;
            }
        }

        if ($ok) {
            $indents = mb_strlen($matches[1]);

            $this->lineno++;
            $this->consume($matches[0]);

            if ($this->length() && (' ' == $this->input[0] || "\t" == $this->input[0])) {
                throw new \Exception('Invalid indentation, you can use tabs or spaces but not both');
            }

            if ($this->length() && $this->input[0] === "\n") {
                return $this->token('newline');
            }

            if (count($this->indentStack) && $indents < $this->indentStack[0]) {
                while (count($this->indentStack) && $indents < $this->indentStack[0]) {
                    array_push($this->stash, $this->token('outdent'));
                    array_shift($this->indentStack);
                }
                return array_pop($this->stash);
            }

            if ($indents && count($this->indentStack) && $indents == $this->indentStack[0]) {
                return $this->token('newline');
            }

            if ($indents) {
                array_unshift($this->indentStack, $indents);
                return $this->token('indent', $indents);
            }

            return $this->token('newline');
        }
    }

    protected function scanPipelessText() {
        if ($this->pipeless && "\n" != $this->input[0]) {
            $i = mb_strpos($this->input, "\n");

            if ($i === false) {
                $i = $this->length();
            }

            $str = mb_substr($this->input,0,$i); // do not include the \n char
            $this->consume($str);
            return $this->token('text', $str);
        }
    }

    protected function scanColon() {
        return $this->scan('/^: */', ':');
    }

    public function nextToken() {
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
            or $r = $this->scanWhile()
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
            or $r = $this->scanText();

        return $r;
    }

    /**
     * @deprecated Use lookahead instead
     */
    public function predictToken($number = 1) {
        $this->lookahead($number);
    }
    /**
     * @deprecated Use advance instead
     */
    public function getAdvancedToken() {
        return $this->avance();
    }
}
