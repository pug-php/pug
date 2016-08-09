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

    protected function parseSpace($states, $escapedAttribute, &$val, &$key, $char, $previousNonBlankChar, $nextChar)
    {
        switch ($states->current()) {
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
                $states->push('key');
                $val = trim($val);
                $key = trim($key);

                if (empty($key)) {
                    return false;
                }

                $key = preg_replace(
                    array('/^[\'\"]|[\'\"]$/', '/\!/'), '', $key
                );
                $this->token->escaped[$key] = $escapedAttribute;

                $this->token->attributes[$key] = ('' === $val) ? true : $this->interpolate($val);

                $key = '';
                $val = '';
        }

        return true;
    }

    protected function replaceInterpolationsInStrings($match)
    {
        $quote = $match[1];

        return str_replace('\\#{', '#{', preg_replace_callback('/(?<!\\\\)#{([^}]+)}/', function ($match) use ($quote) {
            return $quote . ' . ' . CommonUtils::addDollarIfNeeded(preg_replace_callback(
                    '/(?<![a-zA-Z0-9_\$])(\$?[a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)(?![a-zA-Z0-9_])/',
                    function ($match) {
                        return CommonUtils::getGetter($match[1], $match[2]);
                    },
                    $match[1]
                )) . ' . ' . $quote;
        }, $match[0]));
    }

    protected function interpolate($attr)
    {
        return preg_replace_callback('/([\'"]).*?(?<!\\\\)(?:\\\\\\\\)*\1/', array($this, 'replaceInterpolationsInStrings'), $attr);
    }

    protected function parseEqual($states, &$escapedAttribute, &$val, &$key, $char, $previousChar)
    {
        switch ($states->current()) {
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
                $states->push('val');
        }
    }

    public function parseChar($char, &$nextChar, &$key, &$val, &$quote, $states, &$escapedAttribute, &$previousChar, &$previousNonBlankChar)
    {
        switch ($char) {
            case ',':
            case "\n":
            case "\t":
            case ' ':
                if (!$this->parseSpace($states, $escapedAttribute, $val, $key, $char, $previousNonBlankChar, $nextChar)) {
                    return;
                }
                break;

            case '=':
                $this->parseEqual($states, $escapedAttribute, $val, $key, $char, $previousChar);
                break;

            case '(':
                $states->pushFor('expr', 'val', 'expr');
                $val .= $char;
                break;

            case ')':
                $states->popFor('val', 'expr');
                $val .= $char;
                break;

            case '{':
                $states->pushFor('object', 'val');
                $val .= $char;
                break;

            case '}':
                $states->popFor('object');
                $val .= $char;
                break;

            case '[':
                $states->pushFor('array', 'val');
                $val .= $char;
                break;

            case ']':
                $states->popFor('array');
                $val .= $char;
                break;

            case '"':
            case "'":
                if (!CommonUtils::escapedEnd($val)) {
                    $stringParser = new StringAttribute($char);
                    $stringParser->parse($states, $val, $quote);
                    break;
                }

            default:
                switch ($states->current()) {
                    case 'key':
                    case 'key char':
                        $key .= $char;
                        break;

                    default:
                        $val .= $char;
                        break;
                }
        }
    }

    protected function getParseFunction(&$key, &$val, &$quote, $states, &$escapedAttribute, &$previousChar, &$previousNonBlankChar, $parser)
    {
        return function ($char, $nextChar = '') use (&$key, &$val, &$quote, $states, &$escapedAttribute, &$previousChar, &$previousNonBlankChar, $parser) {
            $parser->parseChar($char, $nextChar, $key, $val, $quote, $states, $escapedAttribute, $previousChar, $previousNonBlankChar);
            $previousChar = $char;
            if (trim($char) !== '') {
                $previousNonBlankChar = $char;
            }
        };
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
        $states = new AttributesState();
        $escapedAttribute = '';
        $previousChar = '';
        $previousNonBlankChar = '';

        $parse = $this->getParseFunction($key, $val, $quote, $states, $escapedAttribute, $previousChar, $previousNonBlankChar, $parser);

        for ($i = 0; $i < strlen($str); $i++) {
            $parse(substr($str, $i, 1), substr($str, $i + 1, 1));
        }

        $parse(',');
    }
}
