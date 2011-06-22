# Jade.php

Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

## Public API

    $parser = new Parser(new Lexer());
    $dumper = new Dumper();

    $jade = new Jade($parser, $dumper);

    // Parse a template (supports both string inputs and files)
    echo $jade->render($template);

## Syntax

