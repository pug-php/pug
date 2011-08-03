<?php
require './work.php';
require './lib/node/Node.php';
require './lib/Dumper.php';
require './lib/Lexer.php';
require './lib/Parser.php';
require './Jade.php';

$jade = new Jade();
echo $jade->render('sample1.jade');
echo PHP_EOL;
echo $jade->render('sample2.jade');

?>
