<?php

namespace Pug;

include_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Application
{
    protected $route;

    public function __construct($srcPath, $pathInfo)
    {
        $this->route = ltrim($pathInfo, '/');

        spl_autoload_register(function ($class) use ($srcPath) {
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
        $path = ltrim($path, '/');
        if ($path === $this->route) {
            $pug    = new Pug();
            $vars   = $callback($path) ?: array();
            $output = $pug->renderFile(__DIR__ . '/' . $path . '.pug', $vars);

            echo $output;
        }
    }
}

$uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null;
$uri = $uri ?: (isset($_SERVER['REQUEST_URI'])
    ? trim(preg_replace('/([^?]*)\?.*$/', '$1', $_SERVER['REQUEST_URI']), '/')
    : null
);
$app = new Application(__DIR__ . '/../src/', $uri ?: (isset($argv, $argv[1]) ? $argv[1] : ''));
