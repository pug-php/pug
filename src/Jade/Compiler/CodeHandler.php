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
            throw new \InvalidArgumentException('Expecting a string of PHP, got: ' . gettype($input), 11);
        }

        if (strlen($input) == 0) {
            throw new \InvalidArgumentException('Expecting a string of PHP, empty string received.', 12);
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

        if (strpos('=,;?', substr($this->input, 0, 1)) !== false) {
            throw new \ErrorException('Expecting a variable name or an expression, got: ' . $this->input, 13);
        }

        preg_match_all(
            '/(?<![<>=!])=(?!>|=)|[\[\]\{\}\(\),;\.]|(?!:):|->/', // punctuation
            preg_replace_callback('/[a-zA-Z0-9\\\\_\\x7f-\\xff]*\((?:[0-9\/%\.\s*+-]++|(?R))*+\)/', function ($match) {
                // no need to keep separators in simple PHP expressions (functions calls, parentheses, calculs)
                return str_repeat(' ', strlen($match[0]));
            }, preg_replace_callback('/([\'"]).*?(?<!\\\\)(?:\\\\{2})*\\1/', function ($match) {
                // do not take separators in strings
                return str_repeat(' ', strlen($match[0]));
            }, $this->input)),
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

        $subCodeHandler = new SubCodeHandler($this, $input, $name);
        $getMiddleString = $subCodeHandler->getMiddleString();
        $handleRecursion = $subCodeHandler->handleRecursion($result);
        $handleCodeInbetween = $subCodeHandler->handleCodeInbetween($separators, $result);
        $getNext = $subCodeHandler->getNext($separators);

        // using next() ourselves so that we can advance the array pointer inside inner loops
        while ($sep = current($separators)) {
            // $sep[0] - the separator string due to PREG_SPLIT_OFFSET_CAPTURE flag
            // $sep[1] - the offset due to PREG_SPLIT_OFFSET_CAPTURE

            if ($sep[0] === null) {
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
                    $call = static::addDollarIfNeeded($call);
                    $varname = $var;
                    array_push($result, "{$var}={$call}");
                    break;

                case '[':
                    if (preg_match('/[a-zA-Z0-9\\\\_\\x7f-\\xff]$/', $varname)) {
                        $varname .= $sep[0] . $innerName;
                        break;
                    }
                case '{':
                    $input = $handleCodeInbetween();

                    $output = array();
                    $key = '';
                    $value = null;
                    $addToOutput = $subCodeHandler->addToOutput($output, $key, $value);
                    $consume = $subCodeHandler->consume();
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
                                        throw new \ErrorException('Parse error on ' . substr($argument, strlen($match[1])), 15);
                                    }
                                    $key .= $match[1];
                                    $value = '';
                                    $consume($argument, $match[0]);
                                    break;
                                case ',':
                                    $consume($argument, $match[0]);
                                    ${is_null($value) ? 'key' : 'value'} .= $match[0];
                                    break;
                            }
                        }
                        ${is_null($value) ? 'key' : 'value'} .= $argument;
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
                    if (($innerName !== false && $innerName !== '') || $sep[0] !== ')') {
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
