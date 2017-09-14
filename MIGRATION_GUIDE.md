# Migrate from pug-php 2 to 3

Here are the migration steps you should follow get an app running with pug-php 2
working with pug-php 3.

## Update the dependency

Supposing you manage your project dependencies with
[composer](https://getcomposer.org/)
(this really is the recommended way), you should run the following command:
```shell
composer require pug-php/pug:3.*
```

Else, go the [releases](https://github.com/pug-php/pug/releases), download
the release and replace your own copy of pug with the archive content.

## New options

### Pretty output

The `prettyprint` should no longer be used and `indentChar` and `indentSize`
has been removed. Now you should just use `pretty` option to prettify the HTML
output. `true` indent with 4 spaces, `false` does not indent, else you can
pass a string to indent with.

### Format options

The `phpSingleLine` option no longer exists and by default, there
is no new lines added, but you can patterns option:
```php
$pug = new Pug([
 'patterns' => [
   'php_handle_code' => "<?php %s ?>\n",
   'php_nested_html' => "<?= %s ?>\n",
 ],
]);
```
 
The `singleQuote` option no longer exists but you can patterns option:
```php
$pug = new Pug([
  'patterns' => [
    'attribute_pattern'         => " %s='%s'",
    'boolean_attribute_pattern' => " %s='%s'",
  ],
]);
```