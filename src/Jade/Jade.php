<?php

namespace Jade;

use Jade\Parser;
use Jade\Lexer;
use Jade\Compiler;

/**
 * Class Jade
 * @package Jade
 */
class Jade {
    /**
     * @var bool
     */
    protected $prettyPrint = false;
    /**
     * @var null
     */
    protected $cachePath = null;

    /**
     * @var string
     */
    protected $wrapperName = 'jade.stream';

    /**
     * Indicate if we registed the stream wrapper,
     * in order to not ask the stream registry each time
     * We need to render a template
     * @var bool
     */
    protected static $isWrapperRegistered = false;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        foreach ($options as $key => $opt)
        {
            if (property_exists($this, $key))
            {
                $this->$key = $opt;
            }
        }
    }

    /**
     * @param $input
     * @return string
     */
    public function compile($input)
    {
        $parser     = new Parser($input);
        $compiler   = new Compiler($this->prettyPrint);

        return $compiler->compile($parser->parse($input));
    }

    /**
     * @param $input
     * @param array $vars
     * @return mixed|string
     */
    public function render($input, array $vars = array())
    {
        $file = $this->cachePath ? $this->cache($input) : $this->stream($input);

        extract($vars);
        return include $file;
    }

    /**
     * Create a stream wrapper to allow
     * the possibility to add $scope variables
     * @param $input
     * @return string
     */
    public function stream($input)
    {
        if (! static::$isWrapperRegistered)
        {
            static::$isWrapperRegistered = true;
            stream_wrapper_register($this->wrapperName, 'Jade\Stream');
        }
        return $this->wrapperName.'://data;'.base64_encode($input);
    }


    /**
     * @param $input
     * @return mixed|string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function cache($input)
    {
        if (! is_file($input))
        {
            throw new \InvalidArgumentException('Only files can be cached.');
        }
        if ($this->cachePath == null || ! is_dir($this->cachePath) )
        {
            throw new \Exception('You must provide correct cache path to Jade for caching.');
        }

        $path = str_replace('//', '/', $this->cachePath . '/' . md5($input) . '.php');
        $cacheTime = ! file_exists($path) ? 0 : filemtime($path);

        // Do not re-parse file if original is older
        if ( $cacheTime && filemtime($input) < $cacheTime )
        {
            return $path;
        }
        if (! is_writable($this->cachePath) )
        {
            throw new \Exception(sprintf('Cache directory must be writable. "%s" is not.', $this->cachePath));
        }

        $rendered = $this->compile($input);
        file_put_contents($path, $rendered);

        return $this->stream($rendered);
    }
}
