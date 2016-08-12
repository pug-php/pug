<?php

namespace Jade;

use Jade\Compiler\CodeHandler;
use Jade\Compiler\MixinVisitor;
use Jade\Parser\Exception as ParserException;

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
    protected $restrictedScope = false;
    /**
     * @var array
     */
    protected $customKeywords = array();
    /**
     * @var Jade
     */
    protected $jade = null;

    /**
     * @var string
     */
    protected $quote;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @param array/Jade $options
     * @param array      $filters
     */
    public function __construct($options = array(), array $filters = array(), $filename = null)
    {
        $this->options = $this->setOptions($options);
        $this->filters = $filters;
        $this->filename = $filename;
    }

    /**
     * Get a jade engine reference or an options array and return needed options.
     *
     * @param array/Jade $options
     *
     * @return array
     */
    protected function setOptions($options)
    {
        $optionTypes = array(
            'prettyprint' => 'boolean',
            'phpSingleLine' => 'boolean',
            'allowMixinOverride' => 'boolean',
            'keepNullAttributes' => 'boolean',
            'filterAutoLoad' => 'boolean',
            'restrictedScope' => 'boolean',
            'indentSize' => 'integer',
            'indentChar' => 'string',
            'customKeywords' => 'array',
        );

        if ($options instanceof Jade) {
            $this->jade = $options;
            $options = array();

            foreach ($optionTypes as $option => $type) {
                $this->$option = $this->jade->getOption($option);
                $options[$option] = $this->$option;
                settype($this->$option, $type);
            }

            $this->quote = $this->jade->getOption('singleQuote') ? '\'' : '"';

            return $options;
        }

        foreach (array_intersect_key($optionTypes, $options) as $option => $type) {
            $this->$option = $options[$option];
            settype($this->$option, $type);
        }

        $this->quote = isset($options['singleQuote']) && $options['singleQuote'] ? '\'' : '"';

        return $options;
    }

    /**
     * Get an option from the jade engine if set or from the options array else.
     *
     * @param string $option
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function getOption($option)
    {
        if (is_null($this->jade)) {
            if (!isset($this->options[$option])) {
                throw new \InvalidArgumentException("$option is not a valid option name.", 28);
            }

            return $this->options[$option];
        }

        return $this->jade->getOption($option);
    }

    /**
     * Get a compiler with the same settings.
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
            throw new \BadMethodCallException(sprintf('Method %s do not exists', $method), 7);
        }

        return call_user_func_array(array($this, $method), $arguments);
    }

    /**
     * @param      $line
     * @param null $indent
     */
    protected function buffer($line, $indent = null)
    {
        if ($indent === true || ($indent === null && $this->prettyprint)) {
            $line = $this->indent() . $line . $this->newline();
        }

        $this->buffer[] = $line;
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
     * @throws \ErrorException
     *
     * @return array
     */
    public function handleCode($input, $name = '')
    {
        $handler = new CodeHandler($input, $name);

        return $handler->parse();
    }

    /**
     * @param $input
     *
     * @throws \ErrorException
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

            if (preg_match('/^("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')(.*)$/', $part[0], $match)) {
                $quote = substr($match[1], 0, 1);

                if (strlen(trim($match[2]))) {
                    throw new \ErrorException('Unexpected value: ' . $match[2], 8);
                }

                array_push($resultsString, $match[1]);

                continue;
            }

            $code = $this->handleCode($part[0]);

            $result = array_merge($result, array_slice($code, 0, -1));
            array_push($resultsString, array_pop($code));
        }

        array_push($result, implode(' . ', $resultsString));

        return $result;
    }

    /**
     * @param string $text
     *
     * @return mixed
     */
    public function interpolate($text)
    {
        return preg_replace_callback('/(\\\\)?([#!]){(.*?)}/', array($this, 'interpolateFromCapture'), $text);
    }

    /**
     * @param array $match
     *
     * @return string
     */
    protected function interpolateFromCapture($match)
    {
        if ($match[1] === '') {
            return $this->escapeIfNeeded($match[2] === '!', $match[3]);
        }

        return substr($match[0], 1);
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function createStatements()
    {
        if (func_num_args() === 0) {
            throw new \InvalidArgumentException('No Arguments provided', 9);
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
                array_push($variables, $arg);
                continue;
            }

            // if we have a php variable assume that the string is good php
            if (strpos('{[', substr($arg, 0, 1)) === false && preg_match('/&?\${1,2}' . static::VARNAME . '|[A-Za-z0-9_\\\\]+::/', $arg)) {
                array_push($variables, $arg);
                continue;
            }

            $code = $this->handleArgumentValue($arg);

            $statements = array_merge($statements, array_slice($code, 0, -1));
            array_push($variables, array_pop($code));
        }

        array_push($statements, $variables);

        return $statements;
    }

    protected function handleArgumentValue($arg)
    {
        if (preg_match('/^"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/', $arg)) {
            return $this->handleString(trim($arg));
        }

        try {
            return $this->handleCode($arg);
        } catch (\Exception $e) {
            // if a bug occur, try to remove comments
            try {
                return $this->handleCode(preg_replace('#/\*(.*)\*/#', '', $arg));
            } catch (\Exception $e) {
                throw new ParserException('Pug.php did not understand ' . $arg, 10, $e);
            }
        }
    }

    /**
     * @param      $code
     * @param null $statements
     *
     * @return string
     */
    protected function createPhpBlock($code, $statements = null)
    {
        if ($statements === null) {
            return '<?php ' . $code . ' ' . $this->closingTag();
        }

        $codeFormat = array_pop($statements);
        array_unshift($codeFormat, $code);

        if (count($statements) === 0) {
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
