<?php

namespace Jade\Lexer;

use Jade\Compiler\CommonUtils;

/**
 * Class Jade\Lexer\Attributes.
 */
class Attributes
{
    protected $token;

    public function __construct($token = null)
    {
        $this->token = $token;
    }

    public function parseSpace(&$states, $state, $interpolate, $escapedAttribute, &$val, &$key, $char, $previousNonBlankChar, $nextChar)
    {
        switch ($state()) {
            case 'expr':
            case 'array':
            case 'string':
            case 'object':
                $val .= $char;
                break;

            default:
                if (
                    ($char === ' ' || $char === "\t") &&
                    (
                        !preg_match('/^[a-zA-Z0-9_\\x7f-\\xff"\'\\]\\)\\}]$/', $previousNonBlankChar) ||
                        !preg_match('/^[a-zA-Z0-9_]$/', $nextChar)
                    )
                ) {
                    $val .= $char;
                    break;
                }
                array_push($states, 'key');
                $val = trim($val);
                $key = trim($key);

                if (empty($key)) {
                    return false;
                }

                $key = preg_replace(
                    array('/^[\'\"]|[\'\"]$/', '/\!/'), '', $key
                );
                $this->token->escaped[$key] = $escapedAttribute;

                $this->token->attributes[$key] = ('' === $val) ? true : $interpolate($val);

                $key = '';
                $val = '';
        }

        return true;
    }

    /**
     * @return object
     */
    public function parseWith($str)
    {
        $parser = $this;

        $key = '';
        $val = '';
        $quote = '';
        $states = array('key');
        $escapedAttribute = '';
        $previousChar = '';
        $previousNonBlankChar = '';

        $state = function () use (&$states) {
            return $states[count($states) - 1];
        };

        $interpolate = function ($attr) {
            return preg_replace_callback('/([\'"]).*?(?<!\\\\)(?:\\\\\\\\)*\1/', function ($match) {
                $quote = $match[1];

                return str_replace('\\#{', '#{', preg_replace_callback('/(?<!\\\\)#{([^}]+)}/', function ($match) use ($quote) {
                    return $quote . ' . ' . CommonUtils::addDollarIfNeeded($match[1]) . ' . ' . $quote;
                }, $match[0]));
            }, $attr);
        };

        $parse = function ($char, $nextChar = '') use (&$key, &$val, &$quote, &$states, &$escapedAttribute, &$previousChar, &$previousNonBlankChar, $state, $interpolate, $parser) {
            switch ($char) {
                case ',':
                case "\n":
                case "\t":
                case ' ':
                    if (!$parser->parseSpace($states, $state, $interpolate, $escapedAttribute, $val, $key, $char, $previousNonBlankChar, $nextChar)) {
                        return;
                    }
                    break;

                case '=':
                    switch ($state()) {
                        case 'key char':
                            $key .= $char;
                            break;

                        case 'val':
                        case 'expr':
                        case 'array':
                        case 'string':
                        case 'object':
                            $val .= $char;
                            break;

                        default:
                            $escapedAttribute = '!' !== $previousChar;
                            array_push($states, 'val');
                    }
                    break;

                case '(':
                    if ($state() === 'val' || $state() === 'expr') {
                        array_push($states, 'expr');
                    }
                    $val .= $char;
                    break;

                case ')':
                    if ($state() === 'val' || $state() === 'expr') {
                        array_pop($states);
                    }
                    $val .= $char;
                    break;

                case '{':
                    if ($state() === 'val') {
                        array_push($states, 'object');
                    }
                    $val .= $char;
                    break;

                case '}':
                    if ($state() === 'object') {
                        array_pop($states);
                    }
                    $val .= $char;
                    break;

                case '[':
                    if ($state() === 'val') {
                        array_push($states, 'array');
                    }
                    $val .= $char;
                    break;

                case ']':
                    if ($state() === 'array') {
                        array_pop($states);
                    }
                    $val .= $char;
                    break;

                case '':
                    break;

                case '"':
                case "'":
                    if (!CommonUtils::escapedEnd($val)) {
                        $stringParser = new StringAttribute($state, $char);
                        $stringParser->parse($states, $val, $quote);
                        break;
                    }

                default:
                    switch ($state()) {
                        case 'key':
                        case 'key char':
                            $key .= $char;
                            break;

                        default:
                            $val .= $char;
                            break;
                    }
            }
            $previousChar = $char;
            if (trim($char) !== '') {
                $previousNonBlankChar = $char;
            }
        };

        for ($i = 0; $i < mb_strlen($str); $i++) {
            $parse(mb_substr($str, $i, 1), mb_substr($str, $i + 1, 1));
        }

        $parse(',');
    }
}
