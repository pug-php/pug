# Pug-php
[![Latest Stable Version](https://poser.pugx.org/pug-php/pug/v/stable.png)](https://packagist.org/packages/pug-php/pug)
[![Monthly Downloads](https://poser.pugx.org/pug-php/pug/d/monthly)](https://packagist.org/packages/pug-php/pug)
[![Reference Status](https://www.versioneye.com/php/pug-php:pug/reference_badge.svg?style=flat-square)](https://www.versioneye.com/php/pug-php:pug/references)
[![License](https://poser.pugx.org/pug-php/pug/license)](https://packagist.org/packages/pug-php/pug)

[![Build Status](https://travis-ci.org/pug-php/pug.svg?branch=master)](https://travis-ci.org/pug-php/pug)
[![StyleCI](https://styleci.io/repos/59010999/shield?style=flat)](https://styleci.io/repos/59010999)
[![Test Coverage](https://codeclimate.com/github/pug-php/pug/badges/coverage.svg)](https://codecov.io/github/pug-php/pug?branch=master)
[![Code Climate](https://codeclimate.com/github/pug-php/pug/badges/gpa.svg)](https://codeclimate.com/github/pug-php/pug)


**Pug-php** adds inline PHP scripting support to the [Pug](https://pugjs.org) template compiler. Since the version 3, it uses **Phug**, a very customizable Pug template engine made by **Tale-pug** and **Pug-php** developpers as the new PHP Pug engine reference.

##### [Official Phug documentation](https://www.phug-lang.com/)
##### [See Pug-php demo](https://pug-demo.herokuapp.com/)

## Install

First you need composer if you have'nt yet: https://getcomposer.org/download/

Then run:
```sh
composer require pug-php/pug
```

## Pug in your favorite framework

Phalcon: https://github.com/pug-php/pug-phalcon

Symfony: https://github.com/pug-php/pug-symfony

Laravel: https://github.com/BKWLD/laravel-pug

CodeIgniter: https://github.com/pug-php/ci-pug-engine

Yii 2: https://github.com/rmrevin/yii2-pug

Slim 3: https://github.com/MarcelloDuarte/pug-slim

## Use

```php
<?php

include 'vendor/autoload.php';

$pug = new Pug([
    // here you can set options
]);

$pug->displayFile('my-pug-template.pug');
```

Since **Pug-php** 3.1.2, you no longer need to import the class with
`use Pug\Pug;` as we provide an alias.

Main methods are `render`, `renderFile`, `compile`, `compileFile`,
`display`, `displayFile` and `setOption`, see the complete documentation
here: [phug-lang.com](https://www.phug-lang.com).

You can also use the facade to call methods statically:
```php
<?php

use Pug\Facade as PugFacade;

include 'vendor/autoload.php';

$html = PugFacade::renderFile('my-pug-template.pug');
```

## Pug options

Pug options should be passed to the constructor

```php
$pug = new Pug(array(
    'pretty' => true,
    'cache' => 'pathto/writable/cachefolder/'
));
```

## Supports for local variables

```php
$pug = new Pug();
$output = $pug->render('file', array(
    'title' => 'Hello World'
));
```

## New in pug-php 3

pug-php 3 is now aligned on [pugjs 2](github.com/pugjs/pug), it aims to be a perfect
port of the JS project. That's why there are breaking changes in this new version.

[See the changelog to know what's new](https://github.com/pug-php/pug/blob/master/CHANGELOG.md)

[See the migration guide if you want to upgrade from pug-php 2 to 3](https://github.com/pug-php/pug/blob/master/MIGRATION_GUIDE.md)

## Supports for custom filters

Filters must be callable: It can be a class that implements the *__invoke()* method, or an anonymous function.

```php
$pug->filter('escaped', 'My\Callable\Class');

// or

$pug->filter('escaped', function($node, $compiler){
    foreach ($node->block->nodes as $line) {
        $output[] = $compiler->interpolate($line->value);
    }
    return htmlentities(implode("\n", $output));
});
```

### Built-in filters

* :css
* :php
* :javascript
* :escaped
* :cdata

### Install other filters with composer

http://pug-filters.selfbuild.fr/

### Publish your own filter

https://github.com/pug-php/pug-filter-base#readme

## Supports for custom keywords

You can add custom keywords, here are some examples:

**Anonymous function**:
```php
$pug->addKeyword('range', function ($args) {
    list($from, $to) = explode(' ', trim($args));

    return array(
        'beginPhp' => 'for ($i = ' . $from . '; $i <= ' . $to . '; $i++) {',
        'endPhp' => '}',
    );
});

$pug->render('
range 1 3
    p= i
');
```

This will render:
```html
<p>1</p>
<p>2</p>
<p>3</p>
```

Note that the existing ```for..in``` operator will have the precedence on this custom ```for``` keyword.

**Invokable class**:
```php
class UserKeyword
{
    public function __invoke($arguments, $block, $keyWord)
    {
        $badges = array();
        foreach ($block->nodes as $index => $tag) {
            if ($tag->name === 'badge') {
                $href = $tag->getAttribute('color');
                $badges[] = $href['value'];
                unset($block->nodes[$index]);
            }
        }

        return array(
            'begin' => '<div class="' . $keyWord . '" data-name="' . $arguments . '" data-badges="[' . implode(',', $badges) . ']">',
            'end' => '</div>',
        );
    }
}

$pug->addKeyword('user', new UserKeyword());

$pug->render('
user Bob
    badge(color="blue")
    badge(color="red")
    em Registered yesterday
');
```

This will render:
```html
<div class="user" data-name="Bob" data-badges="['blue', 'red']">
    <em>Registered yesterday</em>
</div>
```

A keyword must return an array (containing **begin** and/or **end** entires) or a string (used as a **begin** entry).

The **begin** and **end** are rendered as raw HTML, but you can also use **beginPhp** and **endPhp** as in the first example to render PHP codes that will wrap the rendered block if there is one.

## PHP Helpers functions

If you want to make a php function available in a template or in all of them for convenience, use closures ans pass them like any other variables:

```php
$myClosure = function ($string) {
    return 'Hey you ' . $string . ', out there on your own, can you hear me?';
};

$pug->render('p=$myClosure("Pink")', array('myClosure' => $myClosure));
```

This will render:
```html
<p>Hey you Pink, out there on your own, can you hear me?</p>
```

You can make that closure available to all templates without passing it in render params by using the `share` method:

```php
// ... $pug instantiation
$pug->share('myClosure', $myClosure);
$pug->render('p=$myClosure("Pink")');
```

This will render the same HTML than the previous example. Also note that `share` allow you to pass any type of value.

## Cache

**Important**: to improve performance in production, enable the Pug cache by setting the **cache** option to a writable directory, you can first cache all your template at once (during deployment):
```php
$pug = new Pug(array(
    'cache' => 'var/cache/pug',
);
list($success, $errors) = $pug->cacheDirectory('path/to/pug/templates');
echo "$success files have been cached\n";
echo "$errors errors occurred\n";
```
Be sure any unexpected error occurred and that all your templates in your template directory have been cached.

Then use the same cache directory and template directory in production with the option upToDateCheck to ```false```
to bypass the cache check and automatically use the cache version:
```php
$pug = new Pug(array(
    'cache' => 'var/cache/pug',
    'basedir' => 'path/to/pug/templates',
    'upToDateCheck' => false,
);
$pug->render('path/to/pug/templates/my-page.pug');
```

## Templates from pug-js

First remember pug-php is a PHP template engine. Pug-js and Pug-php provide both a HAML-like syntax
for markup, but for expression and raw code, pug-js use JS, and pug-php use PHP. By default, we did
some magic tricks to transform simple JS syntaxes into PHP. This should help you to migrate smoother
from pug-js if you already have some template but benefit of PHP advantages.

If you start a new project, we highly recommend you to use the following option:
```php
$pug = new Pug(array(
    'expressionLanguage' => 'php'
);
```
It will disable all translations, so you have to use always explicit PHP syntaxes such as:
```pug
- $concat = $foo . $bar
p=$concat
```

If you want expressions closest to JS, you can use:
```php
$pug = new Pug(array(
    'expressionLanguage' => 'js'
);
```
It will allow both PHP stuff and JS stuff in a JS-style syntax. But you must stick to it,
you will not be able to mix PHP and JS styles in this mode.

Finally, you can use the native pug-js engine with:
```php
$pug = new Pug(array(
    'pugjs' => true
);
```

This mode require node and npm installed as it will install **pug-cli** and directly call it.
This mode will flat you local vars (it means complex object like DateTime, or classes with
magic methods will be striginfied in JSON as simple objects) and you will not benefit some
features like mixed indent, pre/post render hooks but in this mode you will get exact same
output as in pug-js.

### Write locals object to json file with pugjs

If your locals object is large it may cause a `RuntimeException` error. This is because
locals object passed directly to pug-cli as argument. To fix this problem you can use
`localsJsonFile` option:

```php
$pug = new Pug(array(
    'pugjs' => true,
    'localsJsonFile' => true
);
```

Then your locals will be written to json file and path to file will be passed to compiler.

## Pug CLI

Pug also provide a CLI tool:

```shell
./vendor/bin/pug render-file dir/my-template.pug --output-file 
```

See the [complete CLI documentation here](https://www.phug-lang.com/#cli)

## Check requirements

To check if all requirements are ready to use Pug, use the requirements method:
```php
$pug = new Pug(array(
    'cache' => 'pathto/writable/cachefolder/'
);
$missingRequirements = array_keys(array_filter($pug->requirements(), function ($valid) {
    return $valid === false;
}));
$missings = count($missingRequirements);
if ($missings) {
    echo $missings . ' requirements are missing.<br />';
    foreach ($missingRequirements as $requirement) {
        switch($requirement) {
            case 'streamWhiteListed':
                echo 'Suhosin is enabled and ' . $pug->getOption('stream') . ' is not in suhosin.executor.include.whitelist, please add it to your php.ini file.<br />';
                break;
            case 'cacheFolderExists':
                echo 'The cache folder does not exists, please enter in a command line : <code>mkdir -p ' . $pug->getOption('cache') . '</code>.<br />';
                break;
            case 'cacheFolderIsWritable':
                echo 'The cache folder is not writable, please enter in a command line : <code>chmod -R +w ' . $pug->getOption('cache') . '</code>.<br />';
                break;
            default:
                echo $requirement . ' is false.<br />';
        }
    }
    exit(1);
}
```

## Contributing

All contributions are welcome, for any bug, issue or merge request (except for secutiry issues) please [refer to CONTRIBUTING.md](CONTRIBUTING.md)

## Security

Please report any security issue or risk by emailing pug@selfbuild.fr. Please don't disclose security bugs publicly until they have been handled by us.



Pug-php recommand

[<img src="http://jet-brains.selfbuild.fr/PhpStorm-text.svg" width="150" height="26">](https://www.jetbrains.com/phpstorm/)
