<?php
require './work.php';
require './lib/node/Node.php';
require './lib/node/BlockNode.php';
require './lib/node/CodeNode.php';
require './lib/node/CommentNode.php';
require './lib/node/DoctypeNode.php';
require './lib/node/FilterNode.php';
require './lib/node/TagNode.php';
require './lib/node/TextNode.php';
require './lib/Dumper.php';
require './lib/Lexer.php';
require './lib/Parser.php';
require './Jade.php';

$jade = new Jade();
echo $jade->render('sample1.jade');
echo PHP_EOL;
echo $jade->render('sample2.jade');

?>
