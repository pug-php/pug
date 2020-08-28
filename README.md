# <img src="http://pug.selfbuild.fr/pug.png" width="32" height="32"> Pug-php

[![Latest Stable Version](https://poser.pugx.org/pug-php/pug/v/stable.png)](https://packagist.org/packages/pug-php/pug)
[![Monthly Downloads](https://poser.pugx.org/pug-php/pug/d/monthly)](https://packagist.org/packages/pug-php/pug)
[![License](https://poser.pugx.org/pug-php/pug/license)](https://packagist.org/packages/pug-php/pug)
[![Build Status](https://travis-ci.org/pug-php/pug.svg?branch=master)](https://travis-ci.org/pug-php/pug)
[![StyleCI](https://styleci.io/repos/59010999/shield?style=flat)](https://styleci.io/repos/59010999)
[![Test Coverage](https://codeclimate.com/github/pug-php/pug/badges/coverage.svg)](https://codecov.io/github/pug-php/pug?branch=master)
[![Code Climate](https://codeclimate.com/github/pug-php/pug/badges/gpa.svg)](https://codeclimate.com/github/pug-php/pug)
[![Dependencies](https://tidelift.com/badges/github/pug-php/pug)](https://tidelift.com/subscription/pkg/packagist-pug-php-pug?utm_source=packagist-pug-php-pug&utm_medium=referral&utm_campaign=readme)

**Pug-php** adds inline PHP scripting support to the [Pug](https://pugjs.org) template compiler. Since version 3, it uses **Phug**, a very customizable Pug template engine made by the **tale-pug** and **pug-php** developers as the new PHP Pug engine reference.

##### [Official Phug documentation](https://www.phug-lang.com/)
##### [See Pug-php demo](https://pug-demo.herokuapp.com/)
##### [Get supported pug-php/pug with the Tidelift Subscription](https://tidelift.com/subscription/pkg/packagist-pug-php-pug?utm_source=packagist-pug-php-pug&utm_medium=referral&utm_campaign=readme)

## Install

First you need composer if you haven't yet: https://getcomposer.org/download/

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

Zend Expressive: https://github.com/kpicaza/infw-pug

## Use

```php
<?php

include 'vendor/autoload.php';

$pug = new Pug([
    // here you can set options
]);

$pug->displayFile('my-pug-template.pug');
```

Since **pug-php** 3.1.2, you no longer need to import the class with
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

pug-php 3 is now aligned on [pugjs 2](https://github.com/pugjs/pug), it aims to be a perfect
implementation of the JS project. That's why there are breaking changes in this new version.

[See the changelog to know what's new](https://github.com/pug-php/pug/blob/master/CHANGELOG.md)

[See the migration guide if you want to upgrade from pug-php 2 to 3](https://github.com/pug-php/pug/blob/master/MIGRATION_GUIDE.md)

## Support for custom filters

Filters must be callable: It can be a class that implements the *\_\_invoke()* method or an anonymous function.

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

## Support for custom keywords

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

A keyword must return an array (containing **begin** and/or **end** entries) or a string (used as a **begin** entry).

The **begin** and **end** are rendered as raw HTML, but you can also use **beginPhp** and **endPhp** like in the first example to render PHP code that will wrap the rendered block if there is one.

## PHP Helper functions

If you want to make a php function available in a template or in all of them for convenience, use closures and pass them like any other variable:

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

First remember pug-php is a PHP template engine. Pug-js and Pug-php provide both, a HAML-like syntax
for markup and some abstraction of the language behind it (loops, conditions, etc.). But for expressions and raw code, pug-js uses JS, and pug-php uses PHP. By default, we do some magic tricks to transform simple JS syntax into PHP. This should help you to migrate smoother from pug-js if you already have some templates, but benefit from the PHP advantages.

If you start a new project, we highly recommend you to use the following option:
```php
$pug = new Pug(array(
    'expressionLanguage' => 'php'
);
```
It will disable all translations, so you always have to use explicit PHP syntax:
```pug
- $concat = $foo . $bar
p=$concat
```

If you want expressions very close to JS, you can use:
```php
$pug = new Pug(array(
    'expressionLanguage' => 'js'
);
```
It will allow both PHP and JS in a JS-style syntax. But you have to stick to it, you will not be able to mix PHP and JS in this mode.

Finally, you can use the native pug-js engine with:
```php
$pug = new Pug(array(
    'pugjs' => true
);
```

This mode requires node and npm to be installed as it will install **pug-cli** and directly call it.
This mode will flatten your local variables (it means complex object like DateTime, or classes with
magic methods will be stringified in JSON to simple objects) and you will not benefit from some
features like mixed indent, pre/post render hooks. But in this mode you will get exact same
output as in pug-js.

### Write locals object to JSON file with pug-js

If your locals object is large it may cause a `RuntimeException`. This is because
locals object passed directly to pug-cli as argument. To fix this problem you can use
the `localsJsonFile` option:

```php
$pug = new Pug(array(
    'pugjs' => true,
    'localsJsonFile' => true
);
```

Then your locals will be written to a JSON file and the path of the file will be passed to the compiler.

## Pug CLI

Pug also provide a CLI tool:

```shell
./vendor/bin/pug render-file dir/my-template.pug --output-file
```

See the [complete CLI documentation here](https://www.phug-lang.com/#cli)

## Check requirements

To check if your environment is ready to use Pug, use the `requirements` method:
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

All contributions are welcome, for any bug, issue or merge request (except for security issues) please [refer to CONTRIBUTING.md](CONTRIBUTING.md)

## Security

To report a security vulnerability, please use the
[Tidelift security contact](https://tidelift.com/security).
Tidelift will coordinate the fix and disclosure.

## Contributors

This project exists thanks to all the people who contribute.
<a href="https://github.com/pug-php/pug/contributors"><img src="https://opencollective.com/pug-php/contributors.svg?width=890&button=false" /></a>

And all the people contributing to our dependencies, in particular:
The [Phug engine](https://github.com/phug-php)
The JS syntax converter [Js-Phpize](https://github.com/pug-php/js-phpize)

### Backers

Thank you to all our backers! 🙏 [[Become a backer](https://opencollective.com/pug-php#backer)]

<a href="https://opencollective.com/pug-php#backers" target="_blank"><img src="https://opencollective.com/pug-php/backers.svg?width=890"></a>

## Sponsors

Support this project by becoming a sponsor. Your logo will show up here with a link to your website. [Become a sponsor](https://opencollective.com/pug-php#sponsor)

<a href="https://opencollective.com/pug-php/sponsor/0/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/0/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/1/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/1/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/2/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/2/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/3/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/3/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/4/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/4/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/5/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/5/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/6/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/6/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/7/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/7/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/8/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/8/avatar.svg"></a>
<a href="https://opencollective.com/pug-php/sponsor/9/website" target="_blank"><img src="https://opencollective.com/pug-php/sponsor/9/avatar.svg"></a>

And a big thank-you to Jet Brains who provides such a great IDE:

[<img src="http://jet-brains.selfbuild.fr/PhpStorm-text.svg" width="150" height="26">](https://www.jetbrains.com/phpstorm/)
