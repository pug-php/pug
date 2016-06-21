<?php

namespace Jade\Compiler;

/**
 * Class Jade\Compiler\CodeHandler.
 */
class CodeHandler extends CompilerUtils
{
    protected $input;
    protected $name;
    protected $separators;

    public function __construct($input, $name)
    {
        if (!is_string($input)) {
            throw new \Exception('Expecting a string of PHP, got: ' . gettype($input));
        }

        if (strlen($input) == 0) {
            throw new \Exception('Expecting a string of PHP, empty string received.');
        }

        $this->input = trim(preg_replace('/\bvar\b/', '', $input));
        $this->name = $name;
        $this->separators = array();
    }

    public function innerCode($input, $name)
    {
        $handler = new static($input, $name);

        return $handler->parse();
    }

    public function parse()
    {
        if ($this->isQuotedString()) {
            return array($this->input);
        }

        if (strpos('=])},;?', substr($this->input, 0, 1)) !== false) {
            throw new \Exception('Expecting a variable name or an expression, got: ' . $this->input);
        }

        preg_match_all(
            '/(?<![<>=!])=(?!>|=)|[\[\]{}(),;.]|(?!:):|->/', // punctuation
            preg_replace_callback('#([\'"]).*(?<!\\\\)(?:\\\\{2})*\\1#', function ($match) {
                return str_repeat(' ', strlen($match[0]));
            }, $this->input),
            $separators,
            PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE
        );

        $this->separators = $separators[0];

        if (count($this->separators) === 0) {
            if (strstr('0123456789-+("\'$', substr($this->input, 0, 1)) === false) {
                $this->input = static::addDollarIfNeeded($this->input);
            }

            return array($this->input);
        }

        // add a pseudo separator for the end of the input
        array_push($this->separators, array(null, strlen($this->input)));

        return $this->parseBetweenSeparators();
    }

    protected function isQuotedString()
    {
        $firstChar = substr($this->input, 0, 1);
        $lastChar = substr($this->input, -1);

        return false !== strpos('"\'', $firstChar) && $lastChar === $firstChar;
    }

    protected function parseBetweenSeparators()
    {
        $input = $this->input;
        $name = $this->name;
        $separators = $this->separators;

        // needs to be public because of the closure $handleRecursion
        $result = array();

        // do not add $ if it is not like a variable
        $varname = static::convertVarPath(substr($input, 0, $separators[0][1]), '/^%s/');
        if ($separators[0][0] !== '(' && $varname !== '' && strstr('0123456789-+("\'$', substr($varname, 0, 1)) === false) {
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

            $innerCode = $host->innerCode($str, $name);

            if (count($innerCode) > 1) {
                $result = array_merge($result, array_slice($innerCode, 0, -1));

                return array_pop($innerCode);
            }

            return $innerCode[0];
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

            $innerName = $getMiddleString($sep, $getNext(key($separators)));

            $var = "\$__{$name}";
            switch ($sep[0]) {
                // translate the javascript's obj.attr into php's obj->attr or obj['attr']
                /*
                case '.':
                    $result[] = sprintf("%s=is_array(%s)?%s['%s']:%s->%s",
                        $var, $varname, $varname, $innerName, $varname, $innerName
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
                    while ($currentSeparator && in_array($currentSeparator[0], array('->', '(', ')'))) {
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
                    break;

                case '[':
                case '{':
                    $input = $handleCodeInbetween();

                    $output = array();
                    $key = '';
                    $value = null;
                    $addToOutput = function () use (&$output, &$key, &$value) {
                        foreach (array('key', 'value') as $var) {
                            ${$var} = trim(${$var});
                            if (empty(${$var})) {
                                continue;
                            }
                            if (preg_match('/^\d*[a-zA-Z_]/', ${$var})) {
                                ${$var} = var_export(${$var}, true);
                            }
                            $quote = substr(${$var}, 0, 1);
                        }
                        $output[] = empty($value)
                            ? $key
                            : $key . ' => ' . $value;
                        $key = '';
                        $value = null;
                    };
                    $consume = function (&$argument, $start) {
                        $argument = substr($argument, strlen($start));
                    };
                    foreach ($input as $argument) {
                        $argument = ltrim($argument, '$');
                        $quote = null;
                        while (preg_match('/^(.*?)(=>|[\'",:])/', $argument, $match)) {
                            switch ($match[2]) {
                                case '"':
                                case "'":
                                    if ($quote) {
                                        if (CommonUtils::escapedEnd($match[1])) {
                                            ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                            $consume($argument, $match[0]);
                                            break;
                                        }
                                        $quote = null;
                                        ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                        $consume($argument, $match[0]);
                                        break;
                                    }
                                    $quote = $match[2];
                                    ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                    $consume($argument, $match[0]);
                                    break;
                                case ':':
                                case '=>':
                                    if ($quote) {
                                        ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                        $consume($argument, $match[0]);
                                        break;
                                    }
                                    if (!is_null($value)) {
                                        throw new \Exception('Parse error on ' . substr($argument, strlen($match[1])), 1);
                                    }
                                    $key .= $match[1];
                                    $value = '';
                                    $consume($argument, $match[0]);
                                    break;
                                case ',':
                                    $consume($argument, $match[0]);
                                    if ($quote) {
                                        ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                        break;
                                    }
                                    ${is_null($value) ? 'key' : 'value'} .= $match[1];
                                    $addToOutput();
                                    break;
                            }
                        }
                        $addToOutput();
                    }
                    $varname .= 'array(' . implode(', ', $output) . ')';
                    break;

                case '=':
                    if (preg_match('/^[[:space:]]*$/', $innerName)) {
                        next($separators);
                        $arguments = $handleCodeInbetween();
                        $varname .= '=' . implode($arguments);
                        break;
                    }
                    $varname .= '=' . $handleRecursion(array($sep, end($separators)));
                    break;

                default:
                    if (($innerName !== false && $innerName !== '') || $sep[0] != ')') {
                        $varname .= $sep[0] . $innerName;
                    }
                    break;
            }

            next($separators);
        }
        array_push($result, $varname);

        return $result;
    }
}
