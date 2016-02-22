<?php

namespace Jade;

use Jade\Compiler\MixinVisitor;

/**
 * Class Jade Compiler.
 */
class Compiler extends MixinVisitor
{
    /**
     * Constants and configuration in Compiler/CompilerConfig.php.
     */

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
    protected $options = array();
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
            if (isset($options[$option])) {
                $this->$option = (bool) $options[$option];
            }
        }
        $this->options = $options;
        $this->filters = $filters;
        $this->quote = !isset($options['singleQuote']) || $options['singleQuote'] ? '\'' : '"';
    }

    /**
     * get a compiler with the same settings.
     *
     * @return Compiler
     */
    public function subCompiler()
    {
        return new static($this->options, $this->filters);
    }

    /**
     * php closing tag depanding on the pretty print setting.
     *
     * @return string
     */
    protected function closingTag()
    {
        return '?>' . ($this->prettyprint ? ' ' : '');
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
     * @param        $input
     * @param string $ns
     *
     * @throws \Exception
     *
     * @return array
     */
    public function handleCode($input, $ns = '')
    {
        if (!is_string($input)) {
            throw new \Exception('Expecting a string of PHP, got: ' . gettype($input));
        }

        if (strlen($input) == 0) {
            throw new \Exception('Expecting a string of PHP, empty string received.');
        }

        $input = trim(preg_replace('/\bvar\b/', '', $input));

        // needs to be public because of the closure $handleRecursion
        $result = array();

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
        $handleRecursion = function ($arg, $ns = '') use ($input, &$result, $host, $get_middle_string) {
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

        $handleCodeInbetween = function () use (&$separators, $ns, $handleRecursion, $input) {
            $arguments = array();
            $count = 1;

            $start = current($separators);
            $endPair = array(
                '[' => ']',
                '{' => '}',
                '(' => ')',
                ',' => false,
            );
            $open = $start[0];
            if (!isset($open)) {
                return $arguments;
            }
            $close = $endPair[$start[0]];

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
                    $arg = $handleRecursion(array($start, $end), $tmp_ns);

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
                    $arguments = $handleCodeInbetween();
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
                    $arguments = $handleCodeInbetween();
                    if ($arguments) {
                        $varname = $varname . ', ' . implode(', ', $arguments);
                    }
                    //array_push($result, $varname);

                    break;

                /*case '[':
                    $arguments = $handleCodeInbetween();
                    $varname = $varname . '[' . implode($arguments) . ']';

                    break;*/

                case '=':
                    if (preg_match('/^[[:space:]]*$/', $name)) {
                        next($separators);
                        $arguments = $handleCodeInbetween();
                        $varname = $varname . ' = ' . implode($arguments);
                    } else {
                        $varname = "{$varname} = " . $handleRecursion(array($sep, end($separators)));
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

        foreach ($separators as $part) {
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

            if (preg_match('/^([\'"]).*?\1/', $arg)) {
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
}
