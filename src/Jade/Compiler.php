<?php

namespace Jade;

use Jade\Compiler\MixinVisitor;

/**
 * Class Jade Compiler.
 */
class Compiler extends MixinVisitor
{
    /**
     * @const string
     */
    const VARNAME = '[a-zA-Z\\\\\\x7f-\\xff][a-zA-Z0-9\\\\_\\x7f-\\xff]*';

    /**
     * @const string
     */
    const ESCAPED = 'echo htmlspecialchars(%s)';

    /**
     * @const string
     */
    const UNESCAPED = 'echo \\Jade\\Compiler::strval(%s)';

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
    protected $buffer = array();
    /**
     * @var array
     */
    protected $filters = array();
    /**
     * @var bool
     */
    protected $prettyprint = false;
    /**
     * @var bool
     */
    protected $phpSingleLine = false;
    /**
     * @var bool
     */
    protected $allowMixinOverride = false;
    /**
     * @var bool
     */
    protected $keepNullAttributes = false;
    /**
     * @var bool
     */
    protected $terse = true;
    /**
     * @var bool
     */
    protected $withinCase = false;
    /**
     * @var int
     */
    protected $indents = 0;

    /**
     * @var bool
     */
    public static $jsonEncodeDatas = false;

    /**
     * @var array
     */
    protected $doctypes = array(
        '5' => '<!DOCTYPE html>',
        'html' => '<!DOCTYPE html>',
        'default' => '<!DOCTYPE html>',
        'xml' => '<?xml version="1.0" encoding="utf-8" ?>',
        'transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        '1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        'basic' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">',
        'mobile' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" "http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">',
    );

    /**
     * @var array
     */
    protected $selfClosing = array('meta', 'img', 'link', 'input', 'source', 'area', 'base', 'col', 'br', 'hr');

    /**
     * @var array
     */
    protected $phpKeywords = array('true', 'false', 'null', 'switch', 'case', 'default', 'endswitch', 'if', 'elseif', 'else', 'endif', 'while', 'endwhile', 'do', 'for', 'endfor', 'foreach', 'endforeach', 'as', 'unless');

    /**
     * @var array
     */
    protected $phpOpenBlock = array('switch', 'if', 'else if', 'elseif', 'else', 'while', 'do', 'foreach', 'for', 'unless');

    /**
     * @var array
     */
    protected $phpCloseBlock = array('endswitch', 'endif', 'endwhile', 'endfor', 'endforeach');

    /**
     * @var string
     */
    protected $quote;

    /**
     * @param bool  $prettyprint
     * @param array $filters
     */
    public function __construct(array $options = array(), array $filters = array())
    {
        foreach (array(
            'prettyprint',
            'phpSingleLine',
            'allowMixinOverride',
            'keepNullAttributes',
        ) as $option) {
            $this->$option = (bool) $options[$option];
        }
        $this->filters = $filters;
        $this->quote = $options['singleQuote'] ? '\'' : '"';
    }

    protected function closingTag()
    {
        return '?>' . ($this->prettyprint ? ' ' : '');
    }

    /**
     * value treatment if it must not be escaped.
     *
     * @param string  input value
     *
     * @return string
     */
    public static function strval($val)
    {
        return is_array($val) || is_null($val) || is_bool($val) || is_int($val) || is_float($val) ? json_encode($val) : strval($val);
    }

    /**
     * record a closure as a mixin block during execution jade template time.
     *
     * @param string  mixin name
     * @param string  mixin block treatment
     */
    public static function recordMixinBlock($name, $func = null, $terminate = false)
    {
        static $mixinBlocks = null;
        if (is_null($mixinBlocks)) {
            $mixinBlocks = array();
        }
        $isArray = isset($mixinBlocks[$name]) && is_array($mixinBlocks[$name]);
        if (is_null($func)) {
            if ($isArray) {
                if ($terminate) {
                    array_pop($mixinBlocks);
                } elseif (count($mixinBlocks[$name])) {
                    return $mixinBlocks[$name];
                }
            }
        } else {
            if (!$isArray) {
                $mixinBlocks[$name] = array();
            }
            array_push($mixinBlocks[$name], $func);
        }
    }

    /**
     * record a closure as a mixin block during execution jade template time.
     *
     * @param string  mixin name
     * @param string  mixin block treatment
     */
    public static function callMixinBlock($name, $attributes = array())
    {
        $mixinBlocks = static::recordMixinBlock($name);
        if (is_array($mixinBlocks)) {
            $func = end($mixinBlocks);
            if (is_callable($func)) {
                call_user_func($func, $attributes);
            }
        }
    }

    /**
     * end of the record a closure as a mixin block.
     *
     * @param string  mixin name
     */
    public static function terminateMixinBlock($name)
    {
        static::recordMixinBlock($name, null, true);
    }

    /**
     * @param $node
     *
     * @return string
     */
    public function compile($node)
    {
        $this->visit($node);

        $code = implode('', $this->buffer);

        // Separate in several lines to get a useable line number in case of an error occurs
        if ($this->phpSingleLine) {
            $code = str_replace(array('<?php', '?>'), array("<?php\n", "\n" . $this->closingTag()), $code);
        }
        // Remove the $ wich are not needed
        return $code;
    }

    /**
     * @param $method
     * @param $arguments
     *
     * @throws \BadMethodCallException If the 'apply' rely on non existing method
     *
     * @return mixed
     */
    protected function apply($method, $arguments)
    {
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(sprintf('Method %s do not exists', $method));
        }

        return call_user_func_array(array($this, $method), $arguments);
    }

    /**
     * @param      $line
     * @param null $indent
     */
    protected function buffer($line, $indent = null)
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
     *
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
        $str = trim($str);
        $ok = preg_match("/^{$const_regex}$/", $str);

        // test agains a array of constants
        if (!$attr && !$ok && (0 === strpos($str, 'array(') || 0 === strpos($str, '['))) {

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
     * @param string $call
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function addDollarIfNeeded($call)
    {
        if ($call === 'Inf') {
            throw new \Exception($call . ' cannot be read from PHP', 1);
        }
        if ($call === 'undefined') {
            return 'null';
        }
        if ($call[0] !== '$' && $call[0] !== '\\' && !preg_match('#^(?:' . static::VARNAME . '\\s*\\(|(?:null|false|true)(?![a-z]))#i', $call)) {
            $call = '$' . $call;
        }

        return $call;
    }

    /**
     * @param        $input
     * @param string $ns
     *
     * @throws \Exception
     *
     * @return array
     */
    public function handleCode($input, $ns = '')
    {
        $input = trim(preg_replace('/\bvar\b/', '', $input));

        // needs to be public because of the closure $handle_recursion
        $result = array();

        if (!is_string($input)) {
            throw new \Exception('Expecting a string of javascript, got: ' . gettype($input));
        }

        if (strlen($input) == 0) {
            throw new \Exception('Expecting a string of javascript, empty string received.');
        }

        if (false !== strpos('"\'', $input[0]) && substr($input, -1) === $input[0]) {
            return array($input);
        }

        preg_match_all(
            '/(?<![<>=!])=(?!>)|[\[\]{}(),;.]|(?!:):|->/', // punctuation
            preg_replace_callback('#([\'"]).*(?<!\\\\)(?:\\\\{2})*\\1#', function ($match) {
                return str_repeat(' ', strlen($match[0]));
            }, $input),
            $separators,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        array_walk($separators, function (&$sep) {
            $sep = $sep[0];
        });
        reset($separators);

        if (count($separators) == 0) {
            if (strstr('0123456789-+("\'$', $input[0]) === false) {
                $input = static::addDollarIfNeeded($input);
            }

            return array($input);
        }

        // add a pseudo separator for the end of the input
        array_push($separators, array(null, strlen($input)));

        if ($separators[0][1] == 0) {
            throw new \Exception('Expecting a variable name got: ' . $input);
        }

        // do not add $ if it is not like a variable
        $varname = static::convertVarPath(substr($input, 0, $separators[0][1]), '/^%s/');
        if ($separators[0][0] != '(' && strstr('0123456789-+("\'$', $varname[0]) === false) {
            $varname = static::addDollarIfNeeded($varname);
        }

        $get_middle_string = function ($start, $end) use ($input) {
            $offset = $start[1] + strlen($start[0]);

            return substr(
                $input,
                $offset,
                isset($end) ? $end[1] - $offset : strlen($input)
            );
        };

        $host = $this;
        $handle_recursion = function ($arg, $ns = '') use ($input, &$result, $host, $get_middle_string) {
            list($start, $end) = $arg;
            $str = trim($get_middle_string($start, $end));

            if (!strlen($str)) {
                return '';
            }

            $_code = $host->handleCode($str, $ns);

            if (count($_code) > 1) {
                $result = array_merge($result, array_slice($_code, 0, -1));

                return array_pop($_code);
            }

            return $_code[0];
        };

        $handle_code_inbetween = function () use (&$separators, $ns, $handle_recursion, $input) {
            $arguments = array();
            $count = 1;

            $start = current($separators);
            $end_pair = array(
                '[' => ']',
                '{' => '}',
                '(' => ')',
                ',' => false,
            );
            $open = $start[0];
            if (!isset($open)) {
                return $arguments;
            }
            $close = $end_pair[$start[0]];

            do {
                // reset start
                $start = current($separators);

                do {
                    $curr = next($separators);

                    if ($curr[0] == $open) {
                        $count++;
                    }
                    if ($curr[0] == $close) {
                        $count--;
                    }
                } while ($curr[0] != null && $count > 0 && $curr[0] != ',');

                $end = current($separators);

                if ($end != false && $start[1] != $end[1]) {
                    $tmp_ns = $ns * 10 + count($arguments);
                    $arg = $handle_recursion(array($start, $end), $tmp_ns);

                    array_push($arguments, $arg);
                }
            } while ($curr != null && $count > 0);

            if ($close && $count) {
                throw new \Exception($input . "\nMissing closing: " . $close);
            }

            if ($end !== false) {
                next($separators);
            }

            return $arguments;
        };

        $get_next = function ($i) use ($separators) {
            if (isset($separators[$i + 1])) {
                return $separators[$i + 1];
            }
        };

        // using next() ourselves so that we can advance the array pointer inside inner loops
        while (key($separators) !== null) {
            // $sep[0] - the separator string due to PREG_SPLIT_OFFSET_CAPTURE flag
            // $sep[1] - the offset due to PREG_SPLIT_OFFSET_CAPTURE
            $sep = current($separators);

            if ($sep[0] == null) {
                break;
            } // end of string

            $name = $get_middle_string($sep, $get_next(key($separators)));

            $v = "\$__{$ns}";
            switch ($sep[0]) {
                // translate the javascript's obj.attr into php's obj->attr or obj['attr']
                /*
                case '.':
                    $result[] = sprintf("%s=is_array(%s)?%s['%s']:%s->%s",
                        $v, $varname, $varname, $name, $varname, $name
                    );
                    $varname = $v;
                    break;
                //*/

                // funcall
                case '(':
                    $arguments = $handle_code_inbetween();
                    $call = $varname . '(' . implode(', ', $arguments) . ')';
                    $cs = current($separators);
                    $call = static::addDollarIfNeeded($call);
                    while ($cs && ($cs[0] == '->' || $cs[0] == '(' || $cs[0] == ')')) {
                        $call .= $cs[0] . $get_middle_string(current($separators), $get_next(key($separators)));
                        $cs = next($separators);
                    }
                    $varname = $v;
                    array_push($result, "{$v}={$call}");

                    break;

                // mixin arguments
                case ',':
                    $arguments = $handle_code_inbetween();
                    if ($arguments) {
                        $varname = $varname . ', ' . implode(', ', $arguments);
                    }
                    //array_push($result, $varname);

                    break;

                /*case '[':
                    $arguments = $handle_code_inbetween();
                    $varname = $varname . '[' . implode($arguments) . ']';

                    break;*/

                case '=':
                    if (preg_match('/^[[:space:]]*$/', $name)) {
                        next($separators);
                        $arguments = $handle_code_inbetween();
                        $varname = $varname . ' = ' . implode($arguments);
                    } else {
                        $varname = "{$varname} = " . $handle_recursion(array($sep, end($separators)));
                    }

                    break;

                default:
                    if (($name !== false && $name !== '') || $sep[0] != ')') {
                        $varname = $varname . $sep[0] . $name;
                    }
                    break;
            }

            next($separators);
        }
        array_push($result, $varname);

        return $result;
    }

    /**
     * @param $input
     *
     * @throws \Exception
     *
     * @return array
     */
    public function handleString($input)
    {
        $result = array();
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

            // @todo: handleCode() in concat
            $part[0] = trim($part[0]);

            if (preg_match('/^(([\'"]).*?(?<!\\\\)(?:\\\\\\\\)*\2)(.*)$/', $part[0], $match)) {
                if (mb_strlen(trim($match[3]))) {
                    throw new \Exception('Unexpected value: ' . $match[3]);
                }
                array_push($results_string, $match[1]);
            } else {
                $code = $this->handleCode($part[0]);

                $result = array_merge($result, array_slice($code, 0, -1));
                array_push($results_string, array_pop($code));
            }
        }

        array_push($result, implode(' . ', $results_string));

        return $result;
    }

    /**
     * @param $text
     *
     * @return mixed
     */
    public function interpolate($text)
    {
        $ok = preg_match_all('/(\\\\)?([#!]){(.*?)}/', $text, $matches, PREG_SET_ORDER);

        if (!$ok) {
            return $text;
        }

        $i = 1; // str_replace need a pass-by-ref
        foreach ($matches as $m) {

            // \#{dont_do_interpolation}
            if (mb_strlen($m[1]) == 0) {
                $code_str = $this->createCode($m[2] == '!' ? static::UNESCAPED : static::ESCAPED, $m[3]);
                $text = str_replace($m[0], $code_str, $text, $i);
            }
        }

        return str_replace('\\#{', '#{', $text);
    }

    /**
     * @param $anything object|array
     * @param $key mixed key to retrive from the object or the array
     *
     * @return mixed
     */
    public static function getPropertyFromAnything($anything, $key)
    {
        $value = null;

        if (is_array($anything)) {
            $value = isset($anything[$key]) ? $anything[$key] : null;
        }

        if (is_object($anything)) {
            $value = isset($anything->$key) ? $anything->$key : null;
        }

        return $value;
    }

    /**
     * @param $match array regex match
     *
     * @return string
     */
    protected static function convertVarPathCallback($match)
    {
        if (empty($match[1])) {
            $var = $match[0];
        } else {
            $var = ($match[0] === ',' ? ',' : '') . $match[1];
            foreach (explode('.', substr($match[2], 1)) as $name) {
                if (!empty($name)) {
                    $var = '\\Jade\\Compiler::getPropertyFromAnything(' .
                        static::addDollarIfNeeded($var) .
                        ', ' . var_export($name, true) . ')';
                }
            }
        }

        return $var;
    }

    /**
     * Replace var paths in a string.
     *
     * @param $arg string
     * @param $regexp string
     *
     * @return string
     */
    protected static function convertVarPath($arg, $regexp = '/^%s|,%s/')
    {
        $pattern = '\s*(\\${0,2}' . static::VARNAME . ')((\.' . static::VARNAME . ')*)';

        return preg_replace_callback(
            str_replace('%s', $pattern, $regexp),
            array(get_class(), 'convertVarPathCallback'),
            $arg
        );
    }

    /**
     * @throws \Exception
     *
     * @return array
     */
    protected function createStatements()
    {
        if (func_num_args() == 0) {
            throw new \Exception('No Arguments provided');
        }

        $arguments = func_get_args();
        $statements = array();
        $variables = array();

        foreach ($arguments as $arg) {
            $arg = static::convertVarPath($arg);

            // add dollar if missing
            if (preg_match('/^' . static::VARNAME . '(\s*,.+)?$/', $arg)) {
                $arg = static::addDollarIfNeeded($arg);
            }

            // shortcut for constants
            if ($this->isConstant($arg)) {
                if ($arg === 'undefined') {
                    $arg = 'null';
                }
                array_push($variables, $arg);
                continue;
            }

            // if we have a php variable assume that the string is good php
            if (preg_match('/&?\${1,2}' . static::VARNAME . '|::/', $arg)) {
                array_push($variables, $arg);
                continue;
            }

            if (preg_match('/^([\'"]).*?\1/', $arg, $match)) {
                $code = $this->handleString(trim($arg));
            } else {
                try {
                    $code = $this->handleCode($arg);
                } catch (\Exception $e) {
                    // if a bug occur, try to remove comments
                    try {
                        $code = $this->handleCode(preg_replace('#/\*(.*)\*/#', '', $arg));
                    } catch (\Exception $e) {
                        throw new \Exception('JadePHP do not understand ' . $arg, 1, $e);
                    }
                }
            }

            $statements = array_merge($statements, array_slice($code, 0, -1));
            array_push($variables, array_pop($code));
        }

        array_push($statements, $variables);

        return $statements;
    }

    /**
     * @param      $code
     * @param null $statements
     *
     * @return string
     */
    protected function createPhpBlock($code, $statements = null)
    {
        if ($statements == null) {
            return '<?php ' . $code . ' ' . $this->closingTag();
        }

        $code_format = array_pop($statements);
        array_unshift($code_format, $code);

        if (count($statements) == 0) {
            $php_string = call_user_func_array('sprintf', $code_format);

            return '<?php ' . $php_string . ' ' . $this->closingTag();
        }

        $stmt_string = '';
        foreach ($statements as $stmt) {
            $stmt_string .= $this->newline() . $this->indent() . $stmt . ';';
        }

        $stmt_string .= $this->newline() . $this->indent();
        $stmt_string .= call_user_func_array('sprintf', $code_format);

        $php_str = '<?php ';
        $php_str .= $stmt_string;
        $php_str .= $this->newline() . $this->indent() . ' ' . $this->closingTag();

        return $php_str;
    }

    /**
     * @param $code
     *
     * @return string
     */
    protected function createCode($code)
    {
        if (func_num_args() > 1) {
            $arguments = func_get_args();
            array_shift($arguments); // remove $code
            $statements = $this->apply('createStatements', $arguments);

            return $this->createPhpBlock($code, $statements);
        }

        return $this->createPhpBlock($code);
    }

    protected static function initArgToNull(&$arg)
    {
        $arg = static::addDollarIfNeeded(trim($arg));
        if (strpos($arg, '=') === false) {
            $arg .= ' = null';
        }
    }

    public static function withMixinAttributes($attributes, $mixinAttributes)
    {
        foreach ($mixinAttributes as $attribute) {
            if ($attribute['name'] === 'class') {
                $attributes['class'] = empty($attributes['class'])
                    ? $attribute['value']
                    : $attributes['class'] . ' ' . $attribute['value'];
            }
        }
        if (isset($attributes['class'])) {
            $attributes['class'] = implode(' ', array_unique(explode(' ', $attributes['class'])));
        }

        return $attributes;
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected static function parseValue($value)
    {
        return json_decode(preg_replace("/'([^']*?)'/", '"$1"', $value));
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected static function decodeValue($value)
    {
        $_value = static::parseValue($value);

        return is_null($_value) ? $value : $_value;
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    protected static function decodeAttributes($attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_array($attribute)) {
                $attribute['value'] = static::decodeValue($attribute['value']);
            } else {
                $attribute = static::decodeValue($attribute);
            }
        }

        return $attributes;
    }

    /**
     * @param $attributes
     */
    public static function displayAttributes($attributes, $quote)
    {
        if (is_array($attributes) || $attributes instanceof Traversable) {
            foreach ($attributes as $key => $value) {
                if ($value !== false && $value !== 'null') {
                    echo ' ' . $key . '=' . $quote . htmlspecialchars($value) . $quote;
                }
            }
        }
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function isDisplayable($value)
    {
        return !is_null($value) && $value !== false;
    }
}
