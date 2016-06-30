<?php

namespace Jade\Compiler;

/**
 * Class Jade\Compiler\SubCodeHandler.
 */
class SubCodeHandler
{
    protected $codeHandler;
    protected $input;
    protected $name;

    public function __construct(CodeHandler $codeHandler, $input, $name)
    {
        $this->codeHandler = $codeHandler;
        $this->input = $input;
        $this->name = $name;
    }

    public function getMiddleString()
    {
        $input = $this->input;

        return function ($start, $end) use ($input) {
            $offset = $start[1] + strlen($start[0]);

            return substr($input, $offset, isset($end) ? $end[1] - $offset : strlen($input));
        };
    }

    public function handleRecursion(&$result)
    {
        $getMiddleString = $this->getMiddleString();
        $codeHandler = $this->codeHandler;

        return function ($arg, $name = '') use (&$result, $codeHandler, $getMiddleString) {
            list($start, $end) = $arg;
            $str = trim($getMiddleString($start, $end));

            if (!strlen($str)) {
                return '';
            }

            $innerCode = $codeHandler->innerCode($str, $name);

            if (count($innerCode) > 1) {
                $result = array_merge($result, array_slice($innerCode, 0, -1));

                return array_pop($innerCode);
            }

            return $innerCode[0];
        };
    }

    public function handleCodeInbetween(&$separators, &$result)
    {
        $handleRecursion = $this->handleRecursion($result);
        $input = $this->input;
        $name = $this->name;

        return function () use (&$separators, $name, $handleRecursion, $input) {
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
            $close = $endPair[$start[0]];

            do {
                // reset start
                $start = current($separators);

                do {
                    $curr = next($separators);

                    if ($curr[0] === $open) {
                        $count++;
                    }
                    if ($curr[0] === $close) {
                        $count--;
                    }
                } while ($curr[0] !== null && $count > 0 && $curr[0] !== ',');

                $end = current($separators);

                if ($end !== false && $start[1] !== $end[1]) {
                    $tmpName = $name * 10 + count($arguments);
                    $arg = $handleRecursion(array($start, $end), $tmpName);

                    array_push($arguments, $arg);
                }
            } while ($curr !== false && $count > 0);

            if ($close && $count > 0) {
                throw new \ErrorException($input . "\nMissing closing: " . $close, 14);
            }

            if ($end !== false) {
                next($separators);
            }

            return $arguments;
        };
    }

    public function getNext($separators)
    {
        return function ($index) use ($separators) {
            if (isset($separators[$index + 1])) {
                return $separators[$index + 1];
            }
        };
    }

    public function addToOutput(&$output, &$key, &$value)
    {
        return function () use (&$output, &$key, &$value) {
            foreach (array('key', 'value') as $var) {
                ${$var} = trim(${$var});
                if (empty(${$var})) {
                    continue;
                }
                if (preg_match('/^\d*[a-zA-Z_]/', ${$var})) {
                    ${$var} = var_export(${$var}, true);
                }
            }
            $output[] = empty($value)
                ? $key
                : $key . ' => ' . $value;
            $key = '';
            $value = null;
        };
    }

    public function consume()
    {
        return function (&$argument, $start) {
            $argument = substr($argument, strlen($start));
        };
    }
}
