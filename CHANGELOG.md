# 3.0.0

- Support complex JS expression everywhere thanks to
[js-phpize](https://github.com/pug-php/js-phpize) now used by default,
example:

```pug
ul: each item in items.filter(function (i) { return i.indexOf('search') !== -1; })
  li=item.toUpperCase()
```

- In pug-php 2, `->render` detected if you pass a file or a string. In
pug-php 3, you must call explicitly `->renderFile` to render a pug file
using its path as first argument or `->render` to render a pug string.

- Drop pug-php engine to use [phug](github.com/phug-php/phug), a new engine we
develop with Talesoft to get common standard and extensible engine for both
pug-php and [tale-pug](https://github.com/Talesoft/tale-pug)

  - `yield` can now be used only in includes and anonymous `block` only in mixins
  - whitespaces between inline tags and texts are now handled strictly

- Drop Suhoshin custom support. Suhoshin is still supported but we no longer
provide detection of missing settings for suhoshin. So if you use suhoshin, it's
on your own to configure it correctly:

  - By default, we use `eval` to render our templates (it's not a security issue
  even if your locals contain user input until you allow your users to modify
  the pug code), so you have to enable eval in your **php.ini**:
  `suhosin.executor.disable_eval = Off`
  
  - If you use the stream adapter, you will need to whiltelist pug.stream
  in your **php.ini**: `suhosin.executor.include.whitelist = pug.stream`

- Missing include throws exception now by default but you can still get the
pug-php 2 behavior with the following option:
```php
$pug->setOption('not_found_template', '.alert.alert-danger Page not found.');
```
This will output `<div class="alert alert-danger">Page not found.</div>` when
you will try to include a file that does not exist.

- New options:
  - `pretty` can be `true` (2 spaces), `false` (no indent) or a string used as
  indent string. New lines comes with consistent indent.

- Following options are deprecated: `prettyprint`

- Following options no longer exist:
  - `indentChar`, `indentSize`: see `pretty` instead.
  - `phpSingleLine`, `singleQuote`: see `patterns` instead.
  - `restrictedScope`: now variables from parent scope are always available:
```pug
mixin test
  p=foo
  block
div
  p=foo
  +test
    p=foo
```
Supposing you pass it as locals: `$pug->render($file, ['foo' => 'bar'])`,
you will get:
```html
<div>
  <p>bar</p>
  <p>bar</p>
  <p>bar</p>
</div>
```
But now you can create scoped variables inside expressions or codes with
the `let` keyword:
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
to get:
```html
<div>
  <p>bar</p>
  <p>2</p>
  <p>1</p>
  <p>3</p>
  <p>bar</p>
</div>
```

- pugjs 2 dropped attribute string interpolation with the pug syntax, and
so we dropped it. You can use simple concatenation instead:

The following code in pug-php 2:
```pug
a(href="?id=#{feature.foo}")
```

Become this in pug-php 3:
```pug
a(href="?id=" + feature.foo)
```

But text interpolation is still valid and can be escaped or not:

```pug
- var danger = '<b>Yop</b>'
p #{danger}
p !{danger}
```

Output this in both pug-php 2 and 3:

```html
<p>&lt;b&gt;Yop&lt;/b&gt;</p>
<p><b>Yop</b></p>
```

- attributes order are not guaranteed, for example, the class attribute
came last in pug-php 2 and first in pug-php 3.

- `cache` option is now only available for files, no longer for strings.

- exceptions and errors have now different types, codes and messages, and
in debug mode, provide information about the pug source line and offset
that caused the error.

- Drop `\Pug\Parser::$includeNotFound` and `notFound` option. Now use
`not_found_template` option instead.