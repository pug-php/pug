<?php
// [q] 2012, sisoftrg@gmail.com

$jade = null;

// init application and php
function init() {
    error_reporting(E_ALL & ~E_NOTICE);
    spl_autoload_register(function($class) {
        if(!strstr($class, 'Jade'))
            return;
        include_once("../" . str_replace("\\", DIRECTORY_SEPARATOR, $class) . '.php');
    });
}

// return cache file name if $file=true or rendered content
function jade($fn, $file = false, $deps = array()) {
    global $jade;
    $time = @filectime($fn);
    foreach($deps as $dn) {
        $x = @filectime($dn);
        if($x === FALSE)
            break;
        if($x > $time)
            $time = $x;
    }
    if($time === FALSE)
        die("can't open jade file '$fn'");

    if(!isset($jade) || !$jade)
        $jade = new Jade\Jade(true);

    if($file) {
        $cn = "cache/$fn.php";
        $to = @filectime($pn);
        if($to === FALSE || $to < $time)
            file_put_contents($cn, $jade->render($fn));
        return $cn;
    }
    return $jade->render($fn);
}

// check for logged-in user or show login dialog
function login() {
    @session_start();
    if(isset($_SESSION['ok']) && $_SESSION['ok']) {
        return true;
    } else {
        if(isset($_SESSION['ok']))
            @session_destroy();
        $ok = false;
        if(isset($_POST['user']) && isset($_POST['pass'])) {
            if($_POST['user'] == 'admin' && $_POST['pass'] == 'admin')
                $ok = true;
        }
        if(!$ok) {
            require(jade('login.jade', true, array('main.jade')));
            die;
        }
        $_SESSION['ok'] = $ok;
    }
    return true;
}


// main application
init();
login();

// index page = news page for this sample
$page = "news";

if($page)
    require($page . ".php");
require(jade($page ? "{$page}.jade" : "main.jade", true, array('main.jade')));

