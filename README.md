# Jade.php

Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

## Public API

    $jade = new Jade();

    // Parse a template (supports both string inputs and files)
    echo $jade->render($template);

## [Syntax](https://github.com/visionmedia/jade#readme)   