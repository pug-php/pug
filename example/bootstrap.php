<?php
namespace Jade;

error_reporting(E_ALL);
ini_set('display_errors', 1);

class Application {
    protected $route;
    public function __construct($srcPath, $pathInfo)
    {
        if (! isset($pathInfo)){
            $this->route = '/';
        }
        else {
            $this->route = ltrim($pathInfo, '/');
        }
        spl_autoload_register(function($class) use($srcPath) {
            if (! strstr($class, 'Jade')) return;
            include($srcPath . str_replace("\\", DIRECTORY_SEPARATOR, $class) . '.php');
        });
    }
    public function action($path, \Closure $callback)
    {
        if ($path == $this->route) {
            $jade = new Jade;
            $vars = $callback($path) ?: [];
            $jade->render($path. '.jade', $vars);
        }
    }
}
$app = new Application('../src/', $_SERVER['PATH_INFO']);
