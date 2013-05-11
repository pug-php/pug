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
    protected $prettyprint = false;
    /**
     * @var null
     */
    protected $cachePath = null;

    /**
     * @param array $options
     */
    public function __construct(array $options = array()) {

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
     * @param array $scope
     * @return string
     */
    public function compile($input, array $scope = array()) {

        extract($scope);

        $parser     = new Parser($input);
        $compiler   = new Compiler($this->prettyprint);

        $output = $compiler->compile($parser->parse($input));

        return $output;
    }

    /**
     * @param $input
     * @param array $scope
     * @return mixed|string
     */
    public function render($input, array $scope = array())
    {
        return $this->cachePath
            ? $this->cache($input, $scope)
            : $this->compile($input, $scope);
    }

    /**
     * @param $input
     * @param array $scope
     * @return mixed|string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function cache($input, array $scope = array()) {

        if ( $this->cachePath == null || ! is_dir($this->cachePath) ) {
            throw new \Exception('You must provide correct cache path to Jade for caching.');
        }
        if ( !is_writable($this->cachePath) ) {
            throw new \Exception(sprintf('Cache directory must be writable. "%s" is not.', $this->cachePath));
        }
        if ( !is_file($input) ) {
            throw new \InvalidArgumentException('Only file templates can be cached.');
        }

        $cacheKey = 'jade-' . sha1($input);
        $path = $this->cachePath . '/' . $cacheKey . '.php';

        $cacheTime = file_exists($path) ? 0 : filemtime($path);

        if ( $cacheTime && filemtime($input) < $cacheTime ) {
            extract($scope);
            return include $path;
        }

        $rendered = $this->compile($input, $scope);
        file_put_contents($path, $rendered);

        return $rendered;
    }
}
