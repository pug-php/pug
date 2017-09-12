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
