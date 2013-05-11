# Jade.php

Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

##### [The Jade Syntax Reference](https://github.com/visionmedia/jade#readme)

## Implementation details

The fork is a complete rewrite, all the code is ported from the original jade project.

All the features from the original are supported but undertested, including inheritance
and mixins.

## What's new ?

#### Jade options

Jade options should be passed to the Jade construction

```
$jade = new Jade([
	'prettyprint' => true,
	'extension' => '.jade'
	'cache' => 'pathto/writable/cachefolder/'
]);
```

#### Supports for local variables

```
$data['title'] = 'Hello World';
$output = (new Jade())->render('file', $data);
``` 

#### Supports for custom filters

Filters must be callable: It can be a class that implements the __invoke() method, or an anonymous function.

```
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
