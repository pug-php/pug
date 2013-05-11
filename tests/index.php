<?php 

/*
$const_regex = '[ \t]*(([\'"])(?:\\\\.|[^\'"\\\\])*\g{-1}|true|false|null|[0-9]+|\b\b)[ \t]*';
$array_regex = "/array[ \t]*\((?R)\)|\[(?R)\]|({$const_regex}=>)?{$const_regex}(,(?R))?/";
var_dump($const_regex);
var_dump($array_regex);
$isConstant = function ($str) use($const_regex, $array_regex) {
    $ok = preg_match("/^{$const_regex}$/", $str);

    // test agains a array of constants
    if (!$ok) {
        $ok = preg_match($array_regex, $str, $matches);
    }

    return $ok>0 ? true : false;
};

var_dump($isConstant(1));
var_dump($isConstant('"asdf"'));
var_dump($isConstant('[1,2,3,"asdf"]'));
var_dump($isConstant('array()'));
die();
 */

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

function show_tokens($jade) {
    $l = new \Jade\Lexer($jade);
    $indent=0;
    $result = '';

    do{
        $token = $l->nextToken();
        if ($token->type == 'tag') {
            $result .= str_repeat(' ', $indent * 2) . $token->type . ' tag=' . $token->value . "\n";
        }elseif ($token->type == 'text') {
            $result .= str_repeat(' ', $indent * 2) . $token->type . ' text=' . $token->value . "\n";
        }elseif ($token->type == 'id') {
            $result .= str_repeat(' ', $indent * 2) . $token->type . ' value=' . $token->value . "\n";
        }elseif ($token->type == 'attributes') {
            $result .= str_repeat(' ', $indent * 2) . $token->type . ' attrs='; 
            array_walk($token->attributes,function($v,$k) use (&$result) { $result .= $k.':'.$v.',';});
            $result .= "\n";
        }else{
            $result .= str_repeat(' ', $indent * 2) . $token->type . "\n";
        }

        if ($token->type == 'filter') {
            $l->pipeless = true;
        }

        if ($token->type == 'indent'){
            $indent++;
        }elseif($token->type == 'outdent') {
            $indent--;

            if ($l->pipeless) {
                $l->pipeless = false;
            }
        }

    }while($token->type != 'eos');

    return $result;
}

function show_nodes($jade, $file) {
    $p  = new \Jade\Parser($jade, $file);
    $ast= $p->parse();

    $print_nodes = function($node, $indent=0) use (&$print_nodes) {
        $result = '';

        if( is_array($node)) {
            foreach ($node as $n){
                $result .= $print_nodes($n, $indent+1);
            }
        }else{
            if (get_class($node) == 'Nodes\Tag') {
                $result .= str_repeat(' ', $indent * 2) . get_class($node) . ' name=' . $node->name . "\n";
            }elseif (get_class($node) == 'Nodes\Filter') {
                $result .= str_repeat(' ', $indent * 2) . get_class($node) . ' filter_name=' . $node->name . "\n";
            }else{
                $result .= str_repeat(' ', $indent * 2) . get_class($node) . "\n";
            }

            if (isset($node->isBlock) && $node->isBlock == true) {
                $result .= $print_nodes($node->nodes,$indent+1);
            }

            if (isset($node->block)) {
                $result .= $print_nodes($node->block,$indent+1);
            }
        }

        return $result;
    };

    return $print_nodes($ast);
}

function show_php($file) {
    $jade = new \Jade\Jade(true);
    //return htmlspecialchars($jade->render($file));
    return $jade->render($file);
}

setup_autoload();

if (isset($_REQUEST['render'])) {
    $jade   = new \Jade\Jade(true);
    $source = $jade->render(file_get_contents('index.jade'));
    file_put_contents('index.jade.php', $source);
}

$test = false;
if (isset($_REQUEST['test'])) {
    $test   = $_REQUEST['test'];
    $base   = dirname($test) . DIRECTORY_SEPARATOR;
    $html   = basename($test,'.jade');

    $jade   = file_get_contents($test);
    $html   = file_get_contents($base . $html . '.html');
    $tokens = show_tokens($jade);
    $nodes  = show_nodes($jade, $test);
    $php    = show_php($test);
    $test   = true;
}

$nav_list = build_list(find_tests());
require('index.jade.php');

