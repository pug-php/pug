<?php

use Pug\Pug;

require __DIR__ . '/../../vendor/autoload.php';

define('TEMPLATES_DIRECTORY', realpath(str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/../templates')));

define('IGNORE_INDENT', true);

if (!function_exists('_')) {
    function _($value)
    {
        return $value;
    }
}

if (!function_exists('__')) {
    function __($value)
    {
        return $value;
    }
}

function setup_autoload()
{
    // quick setup for autoloading
    $path = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/../');
    $path = realpath($path);
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);

    spl_autoload_register(function ($class) {
        $file = __DIR__ . '/../../src/' . str_replace("\\", DIRECTORY_SEPARATOR, $class) . '.php';
        if(file_exists($file)) {
            require_once($file);
        }
    });
}

function find_tests()
{
    // find the tests
    return glob(TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . '*.pug');
}

function build_list($test_list)
{
    $group_list = [];
    foreach ($test_list as $test) {
        $name = basename($test, '.pug');
        $parts = preg_split('/[.-]/', $name);

        if (!isset($group_list[$parts[0]])) {
            $group_list[$parts[0]] = [];
        }
        $group_list[$parts[0]][] = ['link' => $test, 'name' => $name];
    }

    return $group_list;
}

function get_php_code($code, $vars = [])
{
    $pug = new Pug([
        'debug' => true,
        'singleQuote' => false,
        'prettyprint' => true,
    ]);

    return $pug->render($code, $vars);
}

function get_php_file($file, $vars = [])
{
    $pug = new Pug([
        'debug' => true,
        'singleQuote' => false,
        'prettyprint' => true,
    ]);

    return $pug->renderFile($file, $vars);
}

function compile_php($file)
{
    $pug = new Pug([
        'debug' => true,
        'singleQuote' => false,
        'prettyprint' => true,
    ]);

    return $pug->compile(file_get_contents(TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . $file . '.pug'));
}

function get_html_code($name)
{
    return get_generated_html(get_php_file(TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . $name . '.pug'));
}

function init_tests()
{
    error_reporting(E_ALL);
    setup_autoload();
}

function get_generated_html($contents)
{
    return $contents;
}

function orderWords($words)
{
    if (is_array($words)) {
        return 'class=' . $words[1] . orderWords($words[2]) . $words[1];
    }

    $words = preg_split('`\s+`', $words);
    sort($words);

    return implode(' ', $words);
}

function get_test_result($name, $verbose = false, $moreVerbose = false)
{
    $mergeSpace = IGNORE_INDENT && strpos($name, 'indent.') === false;
    $path = TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . $name;
    $expectedHtml = @file_get_contents($path . '.html');
    if($expectedHtml === false) {
        if($verbose) {
            echo "! sample for test '$name' not found.\n";
        }

        return [false, [$name, null, "! sample for test '$name' not found.\n"]];
    }

    if($verbose) {
        echo "* rendering test '$name'\n";
    }
    try {
        $new = get_php_file($path . '.pug');
    } catch(Exception $err) {
        if($verbose) {
            echo "! FATAL: php exception: " . str_replace("\n", "\n\t", $err) . "\n";
        }

        return [false, [$name, null, "! FATAL: php exception: " . str_replace("\n", "\n\t", $err) . "\n"]];
    }

    if(is_null($new)) {
        return [false, [$name, null, "! FATAL: " . $path . ".pug returns null\n"]];
    }

    $actualHtml = get_generated_html($new);

    $from = ["'", "\r", "<!DOCTYPEhtml>"];
    $to = ['"', '', ''];
    if ($mergeSpace) {
        array_push($from, "\n", "\t", " ");
        array_push($to, '', '', '');
    }
    $expectedHtml = preg_replace_callback('`class\s*=\s*(["\'])([^"\']+)\\1`', 'orderWords', $expectedHtml);
    $actualHtml = preg_replace_callback('`class\s*=\s*(["\'])([^"\']+)\\1`', 'orderWords', $actualHtml);
    if ($mergeSpace) {
        $expectedHtml = preg_replace('`(?<=[\'"])\s(?=>)|(?<=[a-zA-Z0-9:])\s(?=(>|\s[a-zA-Z0-9:]))`', '', $expectedHtml);
        $actualHtml = preg_replace('`(?<=[\'"])\s(?=>)|(?<=[a-zA-Z0-9:])\s(?=(>|\s[a-zA-Z0-9:]))`', '', $actualHtml);
    }
    $minifiedExpectedHtml = str_replace($from, $to, trim($expectedHtml));
    $minifiedActualHtml = str_replace($from, $to, trim($actualHtml));
    $result = [$name, $minifiedExpectedHtml, $minifiedActualHtml];

    if(strcmp($minifiedExpectedHtml, $minifiedActualHtml)) {
        if($verbose) {
            include_once __DIR__ . '/diff.php';
            $actualHtml = preg_replace('`(\r\n|\r|\n)([\t ]*(\r\n|\r|\n))+`', "\n", $actualHtml);
            $expectedHtml = preg_replace('`(\r\n|\r|\n)([\t ]*(\r\n|\r|\n))+`', "\n", $expectedHtml);
            echo Diff::toString(Diff::compare($expectedHtml, $actualHtml)) . "\n";
            /*
            echo "  Expected: $expectedHtml\n";
            echo "  Actual  : $actualHtml\n\n";
            */
        }
        if($moreVerbose) {
            echo "  PHP     : " . compile_php($name);
        }

        return [false, $result];
    }

    return [true, $result];
}

function array_remove(&$array, $value)
{
    if($found = in_array($value, $array)) {
        array_splice($array, array_search($value, $array), 1);
    }

    return $found;
}

function get_tests_results($verbose = false)
{
    global $argv;

    $moreVerbose = array_remove($argv, '--verbose');

    if(! (ini_get('allow_url_include') | 0)) {
        echo "To accelerate the test execution, set in php.ini :\nallow_url_include = On\n\n";
    }

    $nav_list = build_list(find_tests());

    $success = 0;
    $failures = 0;
    $results = [];

    foreach($nav_list as $type => $arr) {
        foreach($arr as $e) {
            $name = $e['name'];

            if($name === 'index' || (
                isset($argv[1]) &&
                false === stripos($argv[0], 'phpunit') &&
                $name !== $argv[1] &&
                $argv[1] !== '.'
            )) {
                continue;
            }

            if($result = get_test_result($name, $verbose, $moreVerbose)) {
                $results[] = $result[1];

                if ($result[0]) {
                    $success++;
                } else {
                    $failures++;
                }
            }
        }
    }

    return [
        'success' => $success,
        'failures' => $failures,
        'results' => $results
    ];
}

init_tests();
