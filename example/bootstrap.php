<?php
namespace Pug;

error_reporting(E_ALL);
ini_set('display_errors', 1);

class Application {
    protected $route;
    public function __construct($srcPath, $pathInfo)
    {
        $this->route = isset($pathInfo) ? ltrim($pathInfo, '/') : '/';

        spl_autoload_register(function($class) use($srcPath) {
            if (
                strstr($class, 'Pug') /* new name */ ||
                strstr($class, 'Jade') /* old name */
            ) {
            	include($srcPath . str_replace("\\", DIRECTORY_SEPARATOR, $class) . '.php');
            }
        });
    }
    public function action($path, \Closure $callback)
    {
        if ($path == $this->route) {
            $pug = new Pug;
            $vars = $callback($path) ?: [];
            $pug->render($path . $pug->getExtension(), $vars);
        }
    }
}
$app = new Application('../src/', $_SERVER['PATH_INFO']);
