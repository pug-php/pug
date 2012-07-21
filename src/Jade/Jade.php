<?php

namespace Jade;

use Jade\Parser;
use Jade\Lexer;
use Jade\Compiler;

class Jade {
    protected $cache;

    public function __construct($cache = null) {
        $this->cache  = $cache;
    }

    public function render($input) {
        $parser = new Parser($input);
        $compiler = new Compiler();

        return $compiler->compile($parser->parse($input));
    }

    public function cache($input) {
        if ( !is_dir($this->cache) ) {
            throw new \Exception('You must provide correct cache path to Jade for caching.');
        }
        if ( !is_file($input) ) {
            throw new \InvalidArgumentException('Only file templates can be cached.');
        } 

        $cacheKey = basename($input, '.jade');
        $path = $this->cache . '/' . $cacheKey . '.php';
		$cacheTime = 0;

		if (file_exists($path)) {
			$cacheTime = filemtime($path);
		}

        if ( $cacheTime && filemtime($input) < $cacheTime ) {
            return $path;
        }

        if ( !is_writable($this->cache) ) {
            throw new \Exception(sprintf('Cache directory must be writable. "%s" is not.', $this->cache));
        }

        $rendered = $this->render($input);
        file_put_contents($path, $rendered);

        return $path;
    }
}
