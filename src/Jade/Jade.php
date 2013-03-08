<?php

namespace Jade;

use Jade\Parser;
use Jade\Lexer;
use Jade\Compiler;

class Jade {
    protected $prettyprint = false;
    protected $cachePath = null;

    public function __construct($cachePath, $prettyprint = false) {
        $this->cachePath = $cachePath;
        $this->prettyprint = $prettyprint;
    }

    public function render($input, $scope=null) {

        if ($scope !== null && is_array($scope)) {
            extract($scope);
        }

        $parser = new Parser($input);
        $compiler = new Compiler($this->prettyprint);

        return $compiler->compile($parser->parse($input));
    }

    public function cache($input) {
        if ( $this->cachePath == null || !is_dir($this->cachePath) ) {
            throw new \Exception('You must provide correct cache path to Jade for caching.');
        }
        if ( !is_file($input) ) {
            throw new \InvalidArgumentException('Only file templates can be cached.');
        }

        $cacheKey = 'jade-' . sha1($input);
        $path = $this->cachePath . '/' . $cacheKey . '.php';
        $cacheTime = 0;

        if (file_exists($path)) {
            $cacheTime = filemtime($path);
        }

        if ( $cacheTime && filemtime($input) < $cacheTime ) {
            return $path;
        }

        if ( !is_writable($this->cachePath) ) {
            throw new \Exception(sprintf('Cache directory must be writable. "%s" is not.', $this->cachePath));
        }

        $rendered = $this->render($input);
        file_put_contents($path, $rendered);

        return $path;
    }
}
