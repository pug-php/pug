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
    protected $filterAutoLoad = true;
    /**
     * @var bool
     */
    protected $terse = true;
    /**
     * @var bool
     */
    protected $withinCase = false;

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
            'prettyprint' => 'boolean',
            'phpSingleLine' => 'boolean',
            'allowMixinOverride' => 'boolean',
            'keepNullAttributes' => 'boolean',
            'filterAutoLoad' => 'boolean',
            'indentSize' => 'integer',
            'indentChar' => 'string',
        ) as $option => $type) {
            if (isset($options[$option])) {
                $this->$option = $options[$option];
                settype($this->$option, $type);
            }
        }
        $this->options = $options;
        $this->filters = $filters;
        $this->quote = !isset($options['singleQuote']) || $options['singleQuote'] ? '\'' : '"';
    }

    /**
     * @param $name
     *
     * @return bool
     */
    protected function getFilter($name)
    {
        // Check that filter is registered
        if (array_key_exists($name, $this->filters)) {
            return $this->filters[$name];
        }

        // Else check if a class with a name that match can be loaded
        $filter = 'Jade\\Filter\\' . implode('', array_map('ucfirst', explode('-', $name)));
        if (class_exists($filter)) {
            return $filter;
        }

        throw new \InvalidArgumentException($name . ': Filter doesn\'t exists');
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

        $code = ltrim(implode('', $this->buffer));

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
     * Test agains a array of constants.
     *
     * @param string $str
     *
     * @return bool|int
     */
    protected function isArrayOfConstants($str)
    {
        $str = trim($str);

        if (0 === strpos($str, 'array(') || 0 === strpos($str, '[')) {

            // This pattern matches against array constants: useful for "data-" attributes (see test attrs-data.jade)
            //
            // simpler regex                - explanation
            //
            // arrray\(\)                   - matches against the old array construct
            // []                           - matches against the new/shorter array construct
            // (const=>)?const(,recursion)  - matches against the value list, values can be a constant or a new array built of constants
            if (preg_match("/array[ \t]*\((?R)\)|\\[(?R)\\]|(" . static::CONSTANT_VALUE . '=>)?' . static::CONSTANT_VALUE . '(,(?R))?/', $str, $matches)) {
                // cant use ^ and $ because the patter is recursive
                if (strlen($matches[0]) == strlen($str)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $str
     *
     * @return bool|int
     */
    protected function isConstant($str)
    {
        return preg_match('/^' . static::CONSTANT_VALUE . '$/', trim($str));
    }

    /**
     * @param        $input
     * @param string $name
     *
     * @throws \Exception
     *
     * @return array
     */
    public function handleCode($input, $name = '')
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

        $getMiddleString = function ($start, $end) use ($input) {
            $offset = $start[1] + strlen($start[0]);

            return substr(
                $input,
                $offset,
                isset($end) ? $end[1] - $offset : strlen($input)
            );
        };

        $host = $this;
        $handleRecursion = function ($arg, $name = '') use ($input, &$result, $host, $getMiddleString) {
            list($start, $end) = $arg;
            $str = trim($getMiddleString($start, $end));

            if (!strlen($str)) {
                return '';
            }

            $_code = $host->handleCode($str, $name);

            if (count($_code) > 1) {
                $result = array_merge($result, array_slice($_code, 0, -1));

                return array_pop($_code);
            }

            return $_code[0];
        };

        $handleCodeInbetween = function () use (&$separators, $name, $handleRecursion, $input) {
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
                    $tmpName = $name * 10 + count($arguments);
                    $arg = $handleRecursion(array($start, $end), $tmpName);

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

        $getNext = function ($index) use ($separators) {
            if (isset($separators[$index + 1])) {
                return $separators[$index + 1];
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

            $_name = $getMiddleString($sep, $getNext(key($separators)));

            $var = "\$__{$name}";
            switch ($sep[0]) {
                // translate the javascript's obj.attr into php's obj->attr or obj['attr']
                /*
                case '.':
                    $result[] = sprintf("%s=is_array(%s)?%s['%s']:%s->%s",
                        $var, $varname, $varname, $_name, $varname, $_name
                    );
                    $varname = $var;
                    break;
                //*/

                // funcall
                case '(':
                    $arguments = $handleCodeInbetween();
                    $call = $varname . '(' . implode(', ', $arguments) . ')';
                    $currentSeparator = current($separators);
                    $call = static::addDollarIfNeeded($call);
                    while ($currentSeparator && ($currentSeparator[0] == '->' || $currentSeparator[0] == '(' || $currentSeparator[0] == ')')) {
                        $call .= $currentSeparator[0] . $getMiddleString(current($separators), $getNext(key($separators)));
                        $currentSeparator = next($separators);
                    }
                    $varname = $var;
                    array_push($result, "{$var}={$call}");

                    break;

                // mixin arguments
                case ',':
                    $arguments = $handleCodeInbetween();
                    if ($arguments) {
                        $varname .= ', ' . implode(', ', $arguments);
                    }
                    //array_push($result, $varname);

                    break;

                /*case '[':
                    $arguments = $handleCodeInbetween();
                    $varname .= '[' . implode($arguments) . ']';

                    break;*/

                case '=':
                    if (preg_match('/^[[:space:]]*$/', $_name)) {
                        next($separators);
                        $arguments = $handleCodeInbetween();
                        $varname .= ' = ' . implode($arguments);
                    } else {
                        $varname .= ' = ' . $handleRecursion(array($sep, end($separators)));
                    }

                    break;

                default:
                    if (($_name !== false && $_name !== '') || $sep[0] != ')') {
                        $varname .= $sep[0] . $_name;
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
        $resultsString = array();

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
                array_push($resultsString, $match[1]);
            } else {
                $code = $this->handleCode($part[0]);

                $result = array_merge($result, array_slice($code, 0, -1));
                array_push($resultsString, array_pop($code));
            }
        }

        array_push($result, implode(' . ', $resultsString));

        return $result;
    }

    /**
     * @param $text
     *
     * @return mixed
     */
    public function interpolate($text)
    {
        $count = preg_match_all('/(\\\\)?([#!]){(.*?)}/', $text, $matches, PREG_SET_ORDER);

        if (!$count) {
            return $text;
        }

        foreach ($matches as $match) {

            // \#{dont_do_interpolation}
            if (mb_strlen($match[1]) == 0) {
                $code = $this->createCode($match[2] == '!' ? static::UNESCAPED : static::ESCAPED, $match[3]);
                $text = str_replace($match[0], $code, $text);
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

        $codeFormat = array_pop($statements);
        array_unshift($codeFormat, $code);

        if (count($statements) == 0) {
            $phpString = call_user_func_array('sprintf', $codeFormat);

            return '<?php ' . $phpString . ' ' . $this->closingTag();
        }

        $stmtString = '';
        foreach ($statements as $stmt) {
            $stmtString .= $this->newline() . $this->indent() . $stmt . ';';
        }

        $stmtString .= $this->newline() . $this->indent();
        $stmtString .= call_user_func_array('sprintf', $codeFormat);

        $phpString = '<?php ';
        $phpString .= $stmtString;
        $phpString .= $this->newline() . $this->indent() . ' ' . $this->closingTag();

        return $phpString;
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
