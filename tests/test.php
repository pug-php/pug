<?php

function setup_autoload() {
    // quick setup for autoloading
    $path = str_replace('/',DIRECTORY_SEPARATOR,dirname(__FILE__).'/../');
    $path = realpath($path);
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);

    spl_autoload_register(function($class){
        require_once(str_replace("\\",DIRECTORY_SEPARATOR,$class).'.php');
    });

}

function find_tests() {
    // find the tests
    $path = str_replace('/',DIRECTORY_SEPARATOR,dirname(__FILE__).'/');
    $path = realpath($path);
    return glob($path .DIRECTORY_SEPARATOR. '*.jade');
}

function build_list($test_list) {
    $group_list = array();
    foreach ($test_list as $test) {
        $name = basename($test, '.jade');
        $parts= preg_split('/[.-]/',$name);

        if (!isset($group_list[$parts[0]])) {
            $group_list[$parts[0]] = array();
        }
        $group_list[$parts[0]][] = array('link' => $test, 'name' => $name);
    }

    return $group_list;
}

function show_php($file) {
    $jade = new \Jade\Jade(true);
    return $jade->render($file);
}

mb_internal_encoding('utf-8');
error_reporting(E_ALL);
setup_autoload();

$nav_list = build_list(find_tests());

foreach($nav_list as $type => $arr)
    foreach($arr as $e) {
        if($e['name'] == 'index' || (isset($argv[1]) && $e['name'] != $argv[1] && $argv[1] != '.'))
            continue;

        $html = @file_get_contents($e['name'] . '.html');
        if($html === FALSE) {
            print "! sample for test '$e[name]' not found.\n";
            continue;
        }

        print "* rendering test '$e[name]'\n";
        try {
            $new = show_php($e['name'] . '.jade');
        } catch(Exception $err) {
            print "! FATAL: php exception: ".str_replace("\n", "\n\t", $err)."\n";
            $new = null;
            die;
        }

        if($new !== null) {
            file_put_contents($e['name'] . '.jade.php', $new);
            $code = `php -d error_reporting="E_ALL & ~E_NOTICE" {$e['name']}.jade.php`;
            file_put_contents($e['name'] . '.jade.html', $code);

            // automatically compare $code and $html here
            $from = array("\n", "\r", "\t", " ", '"', "<!DOCTYPEhtml>");
            $to = array('', '', '', '', "'", '');
            $html = str_replace($from, $to, $html);
            $code = str_replace($from, $to, $code);
            if(strcmp($html, $code)) {
                print "  -$html\n";
                print "  +$code\n\n";
                if(isset($argv[1]) && $argv[1] == '.') // render until first difference
                    die;
            }
        }
    }

