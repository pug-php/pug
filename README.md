# Pug.php
[![Latest Stable Version](https://poser.pugx.org/kylekatarnls/jade-php/v/stable.png)](https://packagist.org/packages/kylekatarnls/jade-php)
[![Total Downloads](https://poser.pugx.org/kylekatarnls/jade-php/downloads.png)](https://packagist.org/packages/kylekatarnls/jade-php)
[![Build Status](https://travis-ci.org/kylekatarnls/jade-php.svg?branch=master)](https://travis-ci.org/kylekatarnls/jade-php)
[![StyleCI](https://styleci.io/repos/17092566/shield?style=flat)](https://styleci.io/repos/17092566)
[![codecov.io](https://codecov.io/github/kylekatarnls/jade-php/coverage.svg?branch=master)](https://codecov.io/github/kylekatarnls/jade-php?branch=master)
[![Code Climate](https://codeclimate.com/github/kylekatarnls/jade-php/badges/gpa.svg)](https://codeclimate.com/github/kylekatarnls/jade-php)
[![Reference Status](https://www.versioneye.com/php/kylekatarnls:jade-php/reference_badge.svg?style=flat)](https://www.versioneye.com/php/kylekatarnls:jade-php/references)


Pug.php adds inline PHP scripting support to the [Pug](http://jade-lang.com) template compiler.

> Pug has been recently re-named from Jade. If you're new to pug, you should install the pug-php/pug package on composer.

##### [The Pug Syntax Reference](https://github.com/pugjs/pug#readme)

## Implementation details

The fork is a complete rewrite, all the code is ported from the original jade project.

All the features from the original are supported but undertested, including inheritance
and mixins.

### Install using composer
```sh
composer require pug-php/pug
composer install
```
[pug-php/pug on packagist.org](https://packagist.org/packages/kylekatarnls/pug)

### Pug in your favorite framework

Phalcon: https://github.com/kylekatarnls/jade-phalcon

Symfony: https://github.com/kylekatarnls/jade-symfony

CodeIgniter: https://github.com/kylekatarnls/ci-jade

### Pug options

Pug options should be passed to the Jade construction

```php
$pug = new Pug(array(
	'prettyprint' => true,
	'extension' => '.pug',
	'cache' => 'pathto/writable/cachefolder/'
);
```

### Supports for local variables

```php
$pug = new Pug();
$output = $pug->render('file', array(
	'title' => 'Hello World'
));
```

### Supports for custom filters

Filters must be callable: It can be a class that implements the __invoke() method, or an anonymous function.

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

#### Built-in filters

* :css
* :php
* :javascript
* :escaped
* :cdata

#### Install other filters with composer

http://pug-filters.selfbuild.fr/

#### Publish your own filter

https://github.com/kylekatarnls/jade-filter-base#readme
