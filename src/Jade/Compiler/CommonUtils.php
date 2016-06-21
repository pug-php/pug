<?php

namespace Jade\Compiler;

/**
 * Class Jade CommonUtils.
 * Common static methods for compiler and lexer classes.
 */
class CommonUtils
{
    /**
     * @param string $call
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function addDollarIfNeeded($call)
    {
        if ($call === 'Inf') {
            throw new \Exception($call . ' cannot be read from PHP', 1);
        }
        if ($call === 'undefined') {
            return 'null';
        }
        if ($call[0] !== '$' && $call[0] !== '\\' && !preg_match('#^(?:' . CompilerConfig::VARNAME . '\\s*\\(|(?:null|false|true)(?![a-z]))#i', $call)) {
            $call = '$' . $call;
        }

        return $call;
    }

    /**
     * Return true if the ending quote of the string is escaped.
     *
     * @param string $quotedString
     *
     * @return bool
     */
    public static function escapedEnd($quotedString)
    {
        $end = substr($quotedString, strlen(rtrim($quotedString, '\\')));

        return substr($end, 0, 1) === '\\' && strlen($end) & 1;
    }
}
