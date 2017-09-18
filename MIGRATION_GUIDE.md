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

## Syntax changes

### Variables assignations

Variables assignations like `myVar = 'myValue` is no longer supported,
now use simply raw code instead: `- myVar = 'myValue`

### Mixin calls

In pug-php 2 `mixin foo()` was valid code for both declarations and calls,
now the only way to call them is: `+foo()` and `mixin` keyword is reserved
for declaration.

### Attribute string interpolation

The attribute interpolation has been dropped (to match pugjs 2 specifications),
but you still can use simple concatenation instead:
```pug
a(href="?id=#{feature.foo}")
```
Become:
```pug
a(href="?id=" + feature.foo)
```
**Important** This only concerns string interpolations in attributes, it's still
valid in texts:
```pug
p #{feature.foo}
```

### Attributes order

Attribute order and merge you get in pug-php 2 could not be the same in
pug-php 3. Example:
```pug
div.d(a="b" class="c")
```
Will output:

| in pug-php 2                    | in pug-php 3                    |
|:-------------------------------:|:-------------------------------:|
| `<div a="b" class="d c"></div>` | `<div class="d c" a="b"></div>` |

## New options

### Includes

Now, by default, including a file that does not exist throw a exception.
We recommend you always include files that cannot be missing and also
avoid any trick that would include a dynamic path.

However you can still set a default template that will replace missing
includes:

```php
$pug = new Pug(['not_found_template' => 'p Coming soon']);
$pug->render(
    "h1 Video page\n".
    "include video.pug\n"
);
```
If `video.pug` does not exists, you will get:
```html
<h1>Video page</h1>
<p>Coming soon</p>
```

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
    'html_expression_escape'    => 'htmlspecialchars(%s, ENT_QUOTES)',
  ],
]);
```
Note: Adding ENT_QUOTES is needed to escape `'` inside attributes values.

The `restrictedScope` option no longer exists. Instead, you can scope
variables with the let keyword:
```pug
mixin test
  p=let foo = 1
  block
div
  p=foo
  p=let foo = 2
  +test
    p=let foo = 3
  p=foo
```

The `keepNullAttributes` option no longer exists, now null and false
attributes are just always removed like in pugjs.

The `terse` no longer exists, now attribute format can be set via patterns
or using the matching doctype.

### Mixin override

The `allowMixinOverride` option no longer exists, but you can get the
same behavior with `mixin_merge_mode`, see below the option values:

| `mixin_merge_mode` (pug-php 3) | `allowMixinOverride` (pug-php 2) | New declaration of a existing mixin |
|--------------------------------|----------------------------------|-------------------------------------|
| `'replace'` (default)          | `true` (default)                 | replace the first one               |
| `'ignore'`                     | `false`                          | is ignored                          |
| `'fail'`                       | no equivalent                    | throws an exception                 |

Caution: this behavior is now only valid for mixins declared in the same
scope (file and block).

## Dropped features

The `cache` option no longer works for string rendering but only for
file rendering.

## Errors

If you used `try {} catch {}` on pug exceptions, be aware that most
exceptions code and messages will be different because theyr are now
handled by our new engine phug.