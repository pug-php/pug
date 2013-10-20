<?php

namespace Jade;

/**
 * Class Compiler
 * @package Jade
 */
class Compiler
{
    /**
     * @var
     */
    protected $xml;

    /**
     * @var
     */
    protected $parentIndents;

    /**
     * @var array
     */
    protected $buffer       = array();
    /**
     * @var array
     */
    protected $filters      = array();
    /**
     * @var bool
     */
    protected $prettyprint  = false;
    /**
     * @var bool
     */
    protected $terse        = false;
    /**
     * @var bool
     */
    protected $withinCase   = false;
    /**
     * @var int
     */
    protected $indents      = 0;

    /**
     * @var array
     */
    protected $doctypes = array(
        '5'             => '<!DOCTYPE html>',
        'html'          => '<!DOCTYPE html>',
        'default'       => '<!DOCTYPE html>',
        'xml'           => '<?xml version="1.0" encoding="utf-8" ?>',
        'transitional'  => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'strict'        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'frameset'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        '1.1'           => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        'basic'         => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">',
        'mobile'        => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" "http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">'
    );

    /**
     * @var array
     */
    protected $selfClosing  = array('meta', 'img', 'link', 'input', 'source', 'area', 'base', 'col', 'br', 'hr');

    /**
     * @var array
     */
    protected $phpKeywords  = array('true','false','null','switch','case','default','endswitch','if','elseif','else','endif','while','endwhile','do','for','endfor','foreach','endforeach','as','unless');

    /**
     * @var array
     */
    protected $phpOpenBlock = array('switch','if','elseif','else','while','do','foreach','for','unless');

    /**
     * @var array
     */
    protected $phpCloseBlock= array('endswitch','endif','endwhile','endfor','endforeach');

    /**
     * @param bool  $prettyprint
     * @param array $filters
     */
    public function __construct($prettyprint = false, array $filters = array())
    {
        $this->prettyprint = $prettyprint;
        $this->filters     = $filters;
    }

    /**
     * @param $node
     *
     * @return string
     */
    public function compile($node)
    {
        $this->visit($node);

        return implode('', $this->buffer);
    }

    /**
     * @param Nodes\Node $node
     * @return array
     */
    public function visit(Nodes\Node $node)
    {
        // TODO: set debugging info
        $this->visitNode($node);

        return $this->buffer;
    }

    /**
     * @param $method
     * @param $arguments
     *
     * @throws \BadMethodCallException  If the 'apply' rely on non existing method
     * @return mixed
     */
    protected function apply($method, $arguments)
    {
        if (! method_exists($this, $method))
        {
           throw new \BadMethodCallException(sprintf('Method %s do not exists', $method));
        }

        switch (count($arguments))
        {
            case 0:
                return $this->{$method}();

            case 1:
                return $this->{$method}($arguments[0]);

            case 2:
                return $this->{$method}($arguments[0], $arguments[1]);

            case 3:
                return $this->{$method}($arguments[0], $arguments[1], $arguments[2]);

            default:
                return call_user_func_array(array($this, $method), $arguments);
        }
    }

    /**
     * @param      $line
     * @param null $indent
     */
    protected function buffer($line, $indent=null)
    {
        if (($indent !== null && $indent == true) || ($indent === null && $this->prettyprint)) {
            $line = $this->indent() . $line . $this->newline();
        }

        $this->buffer[] = $line;
    }

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
     * @param      $str
     * @param bool $attr
     * @return bool|int
     */
    protected function isConstant($str, $attr = false)
    {
        //  This pattern matches against string constants, some php keywords, number constants and a empty string
        //
        //  the pattern without php escaping:
        //
        //      [ \t]*((['"])(?:\\.|[^'"\\])*\g{-1}|true|false|null|[0-9]+|\b\b)[ \t]*
        //
        //  pattern explained:
        //
        //      [ \t]* - we ignore spaces at the beginning and at the end: useful for the recursive pattern bellow
        //
        //      the first part of the unamed subpattern matches strings:
        //          (['"]) - matches a string opening, inside a group because we use a backreference
        //
        //          unamed group to catch the string content:
        //              \\.     - matches any escaped character, including ', " and \
        //              [^'"\\] - matches any character, except the ones that have a meaning
        //
        //          \g{-1}  - relative backreference - http://codesaway.info/RegExPlus/backreferences.html#relative
        //                  - used for two reasons:
        //                      1. reference the same character used to open the string
        //                      2. the pattern is used twice inside the array regex, so cant used absolute or named
        //
        //      the rest of the pattern:
        //          true|false|null - language constants
        //          0-9             - number constants
        //          \b\b            - matches a empty string: useful for a empty array
        $const_regex = '[ \t]*(([\'"])(?:\\\\.|[^\'"\\\\])*\g{-1}|true|false|null|undefined|[0-9]+|\b\b)[ \t]*';
        $str= trim($str);
        $ok = preg_match("/^{$const_regex}$/", $str);

        // test agains a array of constants
        if (!$attr && !$ok && (0 === strpos($str,'array(') || 0 === strpos($str,'['))) {

            // This pattern matches against array constants: useful for "data-" attributes (see test attrs-data.jade)
            //
            // simpler regex                - explanation
            //
            // arrray\(\)                   - matches against the old array construct
            // []                           - matches against the new/shorter array construct
            // (const=>)?const(,recursion)  - matches against the value list, values can be a constant or a new array built of constants
            if (preg_match("/array[ \t]*\((?R)\)|\\[(?R)\\]|({$const_regex}=>)?{$const_regex}(,(?R))?/", $str, $matches)) {
                // cant use ^ and $ because the patter is recursive
                if (strlen($matches[0]) == strlen($str)) {
                    $ok = true;
                }
            }
        }

        return $ok;
    }

    /**
     * @param        $input
     * @param string $ns
     * @return array
     * @throws \Exception
     */
    public function handleCode($input, $ns='')
    {
        // needs to be public because of the closure $handle_recursion
        $result = array();

        if (!is_string($input)) {
            throw new \Exception('Expecting a string of javascript, got: ' . gettype($input));
        }

        if (strlen($input) == 0) {
            throw new \Exception('Expecting a string of javascript, empty string received.');
        }

        if ($input[0] == '"' && $input[strlen($input) - 1] == '"') {
            return array($input);
        }

        preg_match_all(
            '/(?<![<>=!])=(?!>)|[\[\]{}(),;.]|(?!:):|->/', // punctuation
            $input,
            $separators,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        $_separators = array();
        foreach ($separators as $sep) {
            array_push($_separators, $sep[0]);
        }
        $separators = $_separators;

        if (count($separators) == 0) {
            if (strchr('0123456789-+("\'$', $input[0]) === FALSE) {
                $input = '$' . $input;
            }

            return array($input);
        }

        // add a pseudo separator for the end of the input
        array_push($separators, array(null, strlen($input)));

        if ($separators[0][1] == 0) {
            throw new \Exception('Expecting a variable name got: ' . $input);
        }

        // do not add $ if it is not like a variable
        $varname = substr($input,0,$separators[0][1]);
        if ($separators[0][0] != '(' && strchr('0123456789-+("\'$', $varname[0]) === FALSE) {
            $varname = '$' . $varname;
        }

        $get_middle_string = function($start, $end) use ($input) {
            $offset = $start[1] + strlen($start[0]);

            return substr(
                $input,
                $offset,
                isset($end) ? $end[1] - $offset: strlen($input)
            );
        };

        $host = $this;
        $handle_recursion = function ($arg, $ns='') use ($input, &$result, $host, $get_middle_string)
        {
            list($start,$end) = $arg;
            $str = trim($get_middle_string($start, $end));

            if (!strlen($str)) {
                return '';
            }

            $_code  = $host->handleCode($str, $ns);

            if (count($_code) > 1) {
                $result = array_merge($result, array_slice($_code, 0, -1));

                return array_pop($_code);
            }

            return $_code[0];
        };

        $handle_code_inbetween = function() use (&$separators, $ns, $handle_recursion) {
            $arguments  = array();
            $count      = 1;

            $start      = current($separators);
            $end_pair   = array('['=>']', '{'=>'}', '('=>')', ','=>false);
            $open       = $start[0];
            if(!isset($open))

                return $arguments;
            $close      = $end_pair[$start[0]];

            do {
                // reset start
                $start = current($separators);

                do {
                    $curr   = next($separators);

                    if ($curr[0] == $open) $count++;
                    if ($curr[0] == $close) $count--;

                } while ($curr[0] != null && $count > 0 && $curr[0] != ',');

                $end    = current($separators);

                if ($end != false && $start[1] != $end[1]) {
                    $tmp_ns = $ns*10 +count($arguments);
                    $arg    = $handle_recursion(array($start, $end), $tmp_ns);

                    array_push($arguments, $arg);
                }

            } while ($curr != null && $count > 0);

            if ($close && $count) {
                throw new \Exception('Missing closing: ' . $close);
            }

            if ($end !== false)
                next($separators);

            return $arguments;
        };

        $get_next = function ($i) use ($separators) {
            if (isset($separators[$i+1])) {
                return $separators[$i+1];
            }
        };

        // using next() ourselves so that we can advance the array pointer inside inner loops
        while (key($separators) !== null) {
            // $sep[0] - the separator string due to PREG_SPLIT_OFFSET_CAPTURE flag
            // $sep[1] - the offset due to PREG_SPLIT_OFFSET_CAPTURE
            $sep= current($separators);

            if ($sep[0] == null) break; // end of string

            $name = $get_middle_string($sep, $get_next(key($separators)));

            $v = "\$__{$ns}";
            switch ($sep[0]) {
                // translate the javascript's obj.attr into php's obj->attr or obj['attr']
                case '.':
                    $result[] = sprintf("%s=is_array(%s)?%s['%s']:%s->%s",
                        $v, $varname, $varname, $name, $varname, $name
                    );
                    $varname  = $v;
                    break;

                // funcall
                case '(':
                    $arguments  = $handle_code_inbetween();
                    $call       = $varname . '(' . implode(', ', $arguments) . ')';
                    $cs = current($separators);
                    if ($call[0] !== '$') $call = '$' . $call;
                    while ($cs && ($cs[0] == '->' || $cs[0] == '(' || $cs[0] == ')')) {
                        $call .= $cs[0] . $get_middle_string(current($separators), $get_next(key($separators)));
                        $cs = next($separators);
                    }
                    $varname    = $v;
                    array_push($result, "{$v}={$call}");

                    break;

                // mixin arguments
                case ',':
                    $arguments  = $handle_code_inbetween();
                    if($arguments)
                        $varname = $varname . ', ' . implode(', ', $arguments);
                    //array_push($result, $varname);

                    break;

                /*case '[':
                    $arguments = $handle_code_inbetween();
                    $varname = $varname . '[' . implode($arguments) . ']';

                    break;*/

                case '=':
                    if (preg_match('/^[[:space:]]*$/', $name)) {
                        next($separators);
                        $arguments  = $handle_code_inbetween();
                        $varname    = $varname . ' = ' . implode($arguments);
                    } else {
                        $varname    = "{$varname} = " . $handle_recursion(array($sep, end($separators)));
                    }

                    break;

                default:
                    if(($name !== FALSE && $name !== '') || $sep[0] != ')')
                        $varname = $varname . $sep[0] . $name;
                    break;
            }

            next($separators);
        }
        array_push($result, $varname);

        return $result;
    }

    /**
     * @param $input
     * @return array
     * @throws \Exception
     */
    public function handleString($input)
    {
        $result         = array();
        $results_string = array();

        $separators = preg_split(
            '/[+](?!\\()/', // concatenation operator - only js
            $input,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE
        );

        foreach ($separators as $i => $part) {
            // $sep[0] - the separator string due to PREG_SPLIT_OFFSET_CAPTURE flag
            // $sep[1] - the offset due to PREG_SPLIT_OFFSET_CAPTURE
            // @todo: = find original usage of this
            //$sep = substr(
            //    $input,
            //    strlen($part[0]) + $part[1] + 1,
            //    isset($separators[$i+1]) ? $separators[$i+1][1] : strlen($input)
            //);

            // handleCode() and the regex bellow dont like spaces
            $part[0] = trim($part[0]);

            if (preg_match('/^(([\'"]).*?\2)(.*)$/', $part[0], $match)) {
                if (mb_strlen(trim($match[3]))) {
                    throw new \Exception('Unexpected value: ' . $match[3]);
                }
                array_push($results_string, $match[1]);

            } else {
                $code = $this->handleCode($part[0]);

                $result = array_merge($result, array_slice($code,0,-1));
                array_push($results_string, array_pop($code));
            }
        }

        array_push($result, implode(' . ', $results_string));

        return $result;
    }

    /**
     * @param $text
     * @return mixed
     */
    public function interpolate($text)
    {
        $ok = preg_match_all('/(\\\\)?([#!]){(.*?)}/', $text, $matches, PREG_SET_ORDER);

        if (!$ok) {
            return $text;
        }

        $i=1; // str_replace need a pass-by-ref
        foreach ($matches as $m) {

            // \#{dont_do_interpolation}
            if (mb_strlen($m[1]) == 0) {
                if ($m[2] == '!') {
                    $code_str = $this->createCode('echo %s',$m[3]);
                } else {
                    $code_str = $this->createCode('echo htmlspecialchars(%s)',$m[3]);
                }
                $text = str_replace($m[0], $code_str, $text, $i);
            }
        }

        return str_replace('\\#{', '#{', $text);
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function createStatements()
    {
        if (func_num_args()==0) {
            throw new \Exception("No Arguments provided");
        }

        $arguments = func_get_args();
        $statements= array();
        $variables = array();

        foreach ($arguments as $arg) {

            // shortcut for constants
            if ($this->isConstant($arg)) {
                if($arg === 'undefined')
                    $arg = 'null';
                array_push($variables, $arg);
                continue;
            }

            // if we have a php variable assume that the string is good php
            if (preg_match('/&?\${1,2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $arg)) {
                array_push($variables, $arg);
                continue;
            }

            if (preg_match('/^([\'"]).*?\1/', $arg, $match)) {
                $code= $this->handleString(trim($arg));
            } else {
                // TODO: move this to handleCode
                $arg = preg_replace('/\bvar\b/','',$arg);
                $code = $this->handleCode(trim($arg));
            }

            $statements = array_merge($statements, array_slice($code,0,-1));
            array_push($variables, array_pop($code));
        }

        array_push($statements, $variables);

        return $statements;
    }

    /**
     * @param      $code
     * @param null $statements
     * @return string
     */protected function createPhpBlock($code, $statements = null)
    {
        if ($statements == null) {
            return '<?php ' . $code . ' ?>';
        }

        $code_format= array_pop($statements);
        array_unshift($code_format, $code);

        if (count($statements) == 0) {
            $php_string = call_user_func_array('sprintf', $code_format);

            return '<?php ' . $php_string . ' ?>';
        }

        $stmt_string= '';
        foreach ($statements as $stmt) {
            $stmt_string .= $this->newline() . $this->indent() . $stmt . ';';
        }

        $stmt_string .= $this->newline() . $this->indent();
        $stmt_string .= call_user_func_array('sprintf', $code_format);

        $php_str = '<?php ';
        $php_str .= $stmt_string;
        $php_str .= $this->newline() . $this->indent() . ' ?>';

        return $php_str;
    }

    /**
     * @param $code
     * @return string
     */protected function createCode($code)
    {
        if (func_num_args()>1) {
            $arguments = func_get_args();
            array_shift($arguments); // remove $code
            $statements= $this->apply('createStatements', $arguments);

            return $this->createPhpBlock($code, $statements);
        }

        return $this->createPhpBlock($code);
    }


    /**
     * @param Nodes\Node $node
     * @return mixed
     */
    protected function visitNode(Nodes\Node $node)
    {
        $fqn = get_class($node);
        $parts = explode('\\',$fqn);
        $name = $parts[count($parts)-1];
        $method = 'visit' . ucfirst(strtolower($name));

        return $this->$method($node);
    }

    /**
     * @param Nodes\CaseNode $node
     */
    protected function visitCasenode(Nodes\CaseNode $node)
    {
        $within = $this->withinCase;
        $this->withinCase = true;

        // TODO: fix the case hack
        // php expects that the first case statement will be inside the same php block as the switch
        $code_str = 'switch (%s) { '.$this->newline().$this->indent().'case "__phphackhere__": break;';
        $code = $this->createCode($code_str,$node->expr);
        $this->buffer($code);

        $this->indents++;
        $this->visit($node->block);
        $this->indents--;

        $code = $this->createCode('}');
        $this->buffer($code);
        $this->withinCase = $within;
    }

    /**
     * @param Nodes\When $node
     */
    protected function visitWhen(Nodes\When $node)
    {
        if ('default' == $node->expr) {
            $code = $this->createCode('default:');
            $this->buffer($code);
        } else {
            $code = $this->createCode('case %s:',$node->expr);
            $this->buffer($code);
        }

        $this->visit($node->block);

        $code = $this->createCode('break;');
        $this->buffer( $code . $this->newline());
    }

    /**
     * @param Nodes\Literal $node
     */
    protected function visitLiteral(Nodes\Literal $node)
    {
        $str = preg_replace('/\\n/','\\\\n',$node->string);
        $this->buffer($str);
    }

    /**
     * @param Nodes\Block $block
     */
    protected function visitBlock(Nodes\Block $block)
    {
        foreach ($block->nodes as $n) {
            $this->visit($n);
        }
    }

    /**
     * @param Nodes\Doctype $doctype
     * @throws \Exception
     */
    protected function visitDoctype(Nodes\Doctype $doctype=null)
    {
        if (isset($this->hasCompiledDoctype)) {
            throw new \Exception ('Revisiting doctype');
        }
        $this->hasCompiledDoctype = true;

        if (empty($doctype->value) || $doctype == null || !isset($doctype->value)) {
            $doc = 'default';
        } else {
            $doc = strtolower($doctype->value);
        }

        if (isset($this->doctypes[$doc])) {
            $str = $this->doctypes[$doc];
        } else {
            $str = "<!DOCTYPE {$doc}>";
        }

        $this->buffer( $str . $this->newline());

        if (strtolower($str) == '<!doctype html>') {
            $this->terse = true;
        }

        $this->xml = false;
        if ($doc == 'xml') {
            $this->xml = true;
        }
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixin(Nodes\Mixin $mixin)
    {
        $name       = preg_replace('/-/', '_', $mixin->name) . '_mixin';
        $arguments  = $mixin->arguments;
        $block      = $mixin->block;
        $attributes = $mixin->attributes;

        if ($mixin->call) {

            if (!count($attributes)) {
                $attributes = "(isset(\$attributes)) ? \$attributes : array()";
            } else {
                $_attr = array();
                foreach ($attributes as $data) {
                    if ($data['escaped'] === true) {
                        $_attr[$data['name']] = htmlspecialchars($data['value']);
                    } else {
                        $_attr[$data['name']] = $data['value'];
                    }
                }

                //TODO: this adds extra escaping, tests mixin.* failed.
                $attributes = var_export($_attr, true);
                $attributes = "array_merge({$attributes}, (isset(\$attributes)) ? \$attributes : array())";
            }

            if ($arguments === null || empty($arguments)) {
                $code = $this->createPhpBlock("{$name}({$attributes})");
            } else {

                if (!empty($arguments) && !is_array($arguments)) {
                    //$arguments = array($arguments);
                    $arguments = explode(',', $arguments);
                }

                array_unshift($arguments, $attributes);
                $statements= $this->apply('createStatements', $arguments);

                $variables = array_pop($statements);
                $variables = implode(', ', $variables);
                array_push($statements, $variables);

                $arguments = $statements;
                $code_format = "{$name}(%s)";
                array_unshift($arguments, $code_format);

                $code = $this->apply('createCode', $arguments);
            }
            $this->buffer($code);

        } else {
            if ($arguments === null || empty($arguments)) {
                $arguments = array();
            } else
            if (!is_array($arguments)) {
                $arguments = array($arguments);
            }

            //TODO: assign nulls to all varargs for remove php warnings
            array_unshift($arguments, 'attributes');
            $code = $this->createCode("function {$name} (%s) {", implode(',',$arguments));

            $this->buffer($code);
            $this->indents++;
            $this->visit($block);
            $this->indents--;
            $this->buffer($this->createCode('}'));
        }
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTag(Nodes\Tag $tag)
    {
        if (!isset($this->hasCompiledDoctype) && 'html' == $tag->name) {
            $this->visitDoctype();
        }

        $self_closing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;

        if ($tag->name == 'pre') {
            $pp = $this->prettyprint;
            $this->prettyprint = false;
        }

        if (count($tag->attributes)) {
            if ($self_closing) {
                $open = '<' . $tag->name . ' ';
                $close = ($this->terse) ? '>' : '/>';
            } else {
                $open = '<' . $tag->name . ' ';
                $close = '>';
            }

            $this->buffer($this->indent() . $open, false);
            $this->visitAttributes($tag->attributes);
            $this->buffer($close . $this->newline(), false);
        } else {

            if ($self_closing) {
                $html_tag = '<' . $tag->name . (($this->terse) ? '>' : '/>');
            } else {
                $html_tag = '<' . $tag->name . '>';
            }

            $this->buffer($html_tag);
        }

        if (!$self_closing) {
            $this->indents++;
            if (isset($tag->code)) {
                $this->visitCode($tag->code);
            }
            $this->visit($tag->block);
            $this->indents--;

            $this->buffer('</'. $tag->name . '>');
        }

        if ($tag->name == 'pre') {
            $this->prettyprint = $pp;
        }
    }

    /**
     * @param Nodes\Filter $node
     * @throws \InvalidArgumentException
     */
    protected function visitFilter(Nodes\Filter $node)
    {
        // Check that filter is registered
        if (! array_key_exists($node->name, $this->filters)){
            throw new \InvalidArgumentException($node->name.': Filter doesn\'t exists');
        }

        $filter = $this->filters[$node->name];

        // Filters can be either a iFilter implementation, nor a callable
        if (is_string($filter)) {
            $filter = new $filter();
        }
        if (! is_callable($filter)) {
            throw new \InvalidArgumentException($node->name.': Filter must be callable');
        }
        $this->buffer($filter($node, $this));
    }

    /**
     * @param Nodes\Text $text
     */
    protected function visitText(Nodes\Text $text)
    {
        $this->buffer($this->interpolate($text->value));
    }

    /**
     * @param Nodes\Comment $comment
     */
    protected function visitComment(Nodes\Comment $comment)
    {
        if ($comment->buffer) {
            $this->buffer('<!--' . $comment->value . '-->');
        }
    }

    /**
     * @param Nodes\BlockComment $comment
     */
    protected function visitBlockComment(Nodes\BlockComment $comment)
    {
        if (!$comment->buffer) {
            return;
        }

        if (strlen($comment->value) && 0 === strpos(trim($comment->value), 'if')) {
            $this->buffer('<!--[' . trim($comment->value) . ']>');
            $this->visit($comment->block);
            $this->buffer('<![endif]-->');
        } else {
            $this->buffer('<!--' . $comment->value);
            $this->visit($comment->block);
            $this->buffer('-->');
        }
    }

    /**
     * @param Nodes\Code $node
     */
    protected function visitCode(Nodes\Code $node)
    {
        $code = trim($node->value);

        if ($node->buffer) {

            $pattern = $node->escape ? 'echo htmlspecialchars(%s)' : 'echo %s';
            $this->buffer($this->createCode($pattern,$code));
        }
        else {

            $php_open = implode('|', $this->phpOpenBlock);

            if (preg_match("/^[[:space:]]*({$php_open})(.*)/", $code, $matches)) {

                $code = trim($matches[2],'; ');
                while (($len = strlen($code)) > 1 && ($code[0] == '(' || $code[0] == '{') && ord($code[0]) == ord(substr($code, -1)) - 1) {
                    $code = trim(substr($code, 1, $len - 2));
                }

                $index       = count($this->buffer)-1;
                $conditional = '';

                if (isset($this->buffer[$index]) && false !== strpos($this->buffer[$index], $this->createCode('}'))) {
                    // the "else" statement needs to be in the php block that closes the if
                    $this->buffer[$index] = null;
                    $conditional .= '} ';
                }

                $conditional .= '%s';

                if (strlen($code) > 0) {
                    $conditional .= '(%s) {';
                    if ($matches[1] == 'unless') {
                        $conditional = sprintf($conditional, 'if', '!(%s)');
                    } else {
                        $conditional = sprintf($conditional, $matches[1], '%s');
                    }
                    $this->buffer($this->createCode($conditional, $code));
                } else {
                    $conditional .= ' {';
                    $conditional = sprintf($conditional, $matches[1]);

                    $this->buffer($this->createCode($conditional));
                }

            } else {
                $this->buffer($this->createCode('%s', $code));
            }
        }

        if (isset($node->block)) {
            $this->indents++;
            $this->visit($node->block);
            $this->indents--;

            if (!$node->buffer) {
                $this->buffer($this->createCode('}'));
            }
        }
    }

    /**
     * @param $node
     */
    protected function visitEach($node)
    {

        //if (is_numeric($node->obj)) {
        //if (is_string($node->obj)) {
        //$serialized = serialize($node->obj);
        if (isset($node->alternative)) {
            $code = $this->createCode('if (isset(%s) && %s) {',$node->obj, $node->obj);
            $this->buffer($code);
            $this->indents++;
        }

        if (isset($node->key) && mb_strlen($node->key) > 0) {
            $code = $this->createCode('foreach (%s as %s => %s) {',$node->obj,$node->key,$node->value);
        } else {
            $code = $this->createCode('foreach (%s as %s) {',$node->obj,$node->value);
        }

        $this->buffer($code);

        $this->indents++;
        $this->visit($node->block);
        $this->indents--;

        $this->buffer($this->createCode('}'));

        if (isset($node->alternative)) {
            $this->indents--;
            $this->buffer($this->createCode('} else {'));
            $this->indents++;

            $this->visit($node->alternative);
            $this->indents--;

            $this->buffer($this->createCode('}'));
       }
    }

    /**
     * @param $attributes
     */
    protected function visitAttributes($attributes)
    {
        $items = array();
        $classes = array();

        foreach ($attributes as $attr) {
            $key = trim($attr['name']);
            $value = trim($attr['value']);

            if ($this->isConstant($value, $key == 'class')) {
                $value = trim($value,' \'"');
                if($value === 'undefined')
                    $value = 'null';
            } else {
                $json = json_decode(preg_replace("/'([^']*?)'/", '"$1"', $value));

                if ($json !== null && is_array($json) && $key == 'class') {
                    $value = implode(' ', $json);
                } else {
                    // inline this in the tag
                    $pp = $this->prettyprint;
                    $this->prettyprint = false;

                    if ($key == 'class') {
                        $value = $this->createCode('echo (is_array(%1$s)) ? implode(" ", %1$s) : %1$s', $value);
                    } elseif (strpos($key, 'data-') !== false) {
                        $value = $this->createCode('echo json_encode(%s)', $value);
                    } else {
                        $value = $this->createCode('echo %s', $value);
                    }

                    $this->prettyprint = $pp;
                }
            }
            if ($key == 'class') {
                if($value !== 'false' && $value !== 'null' && $value !== 'undefined')
                    array_push($classes, $value);
            } elseif ($value == 'true' || $attr['value'] === true) {
                if ($this->terse) {
                    $items[] = $key;
                } else {
                    $items[] = "{$key}='{$key}'";
                }
            } elseif ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                $items[] = "{$key}='{$value}'";
            }
        }

        if (count($classes)) {
            $items[] = 'class=\'' . implode(' ', $classes) . '\'';
        }

        $this->buffer(implode(' ', $items), false);
    }
}
