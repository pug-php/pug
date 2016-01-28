# Jade.php
[![Latest Stable Version](https://poser.pugx.org/kylekatarnls/jade-php/v/stable.png)](https://packagist.org/packages/kylekatarnls/jade-php)
[![Total Downloads](https://poser.pugx.org/kylekatarnls/jade-php/downloads.png)](https://packagist.org/packages/kylekatarnls/jade-php)
[![Build Status](https://travis-ci.org/kylekatarnls/jade-php.svg?branch=master)](https://travis-ci.org/kylekatarnls/jade-php)
[![StyleCI](https://styleci.io/repos/17092566/shield?style=flat)](https://styleci.io/repos/17092566)
[![codecov.io](https://codecov.io/github/kylekatarnls/jade-php/coverage.svg?branch=master)](https://codecov.io/github/kylekatarnls/jade-php?branch=master)

Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

##### [The Jade Syntax Reference](https://github.com/visionmedia/jade#readme)

## Implementation details

The fork is a complete rewrite, all the code is ported from the original jade project.

All the features from the original are supported but undertested, including inheritance
and mixins.

### Install using composer
[kylekatarnls/jade-php on packagist.org](https://packagist.org/packages/kylekatarnls/jade-php)

## What's new ?

#### Jade options

Jade options should be passed to the Jade construction

```php
$jade = new Jade(array(
	'prettyprint' => true,
	'extension' => '.jade',
	'cache' => 'pathto/writable/cachefolder/'
);
```

#### Supports for local variables

```php
$data['title'] = 'Hello World';
$jade = new Jade();
$output = $jade->render('file', $data);
```

#### Supports for custom filters

Filters must be callable: It can be a class that implements the __invoke() method, or an anonymous function.

```php
$jade->filter('escaped', 'My\Callable\Class');

// or

$jade->filter('escaped', function($node, $compiler){
	foreach ($node->block->nodes as $line) {
		$output[] = $compiler->interpolate($line->value);
	}
	return htmlentities(implode("\n", $output));
});
```
**Built-in filters**:

* :css
* :php
* :javascript
* :escaped
* :cdata
