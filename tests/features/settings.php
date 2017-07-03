<?php

use Pug\Pug;

class PugSettingsTest extends PHPUnit_Framework_TestCase
{
    static private function rawHtml($html, $convertSingleQuote = true)
    {
        $html = str_replace(array("\r", ' '), '', $html);
        if ($convertSingleQuote) {
            $html = strtr($html, "'", '"');
        }
        return trim(preg_replace('`\n{2,}`', "\n", $html));
    }

    static private function simpleHtml($html)
    {
        return trim(preg_replace('`\r\n|\r|(\n\s*| *)\n`', "\n", $html));
    }

    /**
     * keepNullAttributes setting test
     */
    public function testKeepNullAttributes()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'keepNullAttributes' => false,
            'prettyprint' => true,
        ));
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $Pug->render(file_get_contents($templates . 'mixin.attrs.pug'));
        $expected = file_get_contents($templates . 'mixin.attrs.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes disabled');

        $Pug = new Pug(array(
            'singleQuote' => false,
            'keepNullAttributes' => true,
            'prettyprint' => true,
        ));
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $Pug->render(file_get_contents($templates . 'mixin.attrs.pug'));
        $expected = file_get_contents($templates . 'mixin.attrs-keep-null-attributes.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes enabled');
    }

    /**
     * prettyprint setting test
     */
    public function testPrettyprint()
    {
        $template = '
mixin centered(title)
  div.centered(id=attributes.id)
    - if (title)
      h1(class=attributes.class)= title
    block
    - if (attributes.href)
      .footer
        a(href=attributes.href) Back

+centered(\'Section 1\')#Second.foo
  p Some important content.
';

        $Pug = new Pug(array(
            'singleQuote' => true,
            'prettyprint' => true,
        ));
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $Pug->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($expected, $actual, 'Pretty print enabled');

        $Pug = new Pug(array(
            'singleQuote' => true,
            'prettyprint' => false,
        ));
        $actual = preg_replace('`[ \t]+`', ' ', $Pug->render($template));
        $expected =  '<div id=\'Second\' class=\'centered\'><h1 class=\'foo\'>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($expected, $actual, 'Pretty print disabled');
    }

    /**
     * setOption test
     */
    public function testSetOption()
    {
        $template = '
mixin centered(title)
  div.centered(id=attributes.id)
    - if (title)
      h1(class=attributes.class)= title
    block
    - if (attributes.href)
      .footer
        a(href=attributes.href) Back

+centered(\'Section 1\')#Second.foo
  p Some important content.
';

        $Pug = new Pug(array(
            'singleQuote' => true,
            'prettyprint' => false,
        ));
        $this->assertFalse($Pug->getOption('prettyprint'), 'getOption should return current setting');
        $Pug->setOption('prettyprint', true);
        $this->assertTrue($Pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $Pug->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($actual, $expected, 'Pretty print enabled');

        $Pug->setOption('prettyprint', false);
        $this->assertFalse($Pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = preg_replace('`[ \t]+`', ' ', $Pug->render($template));
        $expected =  '<div id=\'Second\' class=\'centered\'><h1 class=\'foo\'>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($actual, $expected, 'Pretty print disabled');
    }

    /**
     * setOptions test
     */
    public function testSetOptions()
    {
        $Pug = new Pug();
        $Pug->setOptions(array(
            'prettyprint' => true,
            'cache' => 'abc',
            'indentChar' => '-',
        ));
        $this->assertTrue($Pug->getOption('prettyprint'));
        $this->assertSame($Pug->getOption('cache'), 'abc');
        $this->assertSame($Pug->getOption('indentChar'), '-');
    }

    /**
     * setCustomOption test
     */
    public function testSetCustomOption()
    {
        $Pug = new Pug();
        $Pug->setCustomOption('i-do-not-exists', 'right');
        $this->assertSame($Pug->getOption('i-do-not-exists'), 'right', 'getOption should return custom setting');
    }

    /**
     * setOptions test
     */
    public function testSetCustomOptions()
    {
        $Pug = new Pug();
        $Pug->setCustomOptions(array(
            'prettyprint' => false,
            'foo' => 'bar',
        ));
        $this->assertFalse($Pug->getOption('prettyprint'));
        $this->assertSame($Pug->getOption('foo'), 'bar');
    }

    /**
     * allowMixinOverride setting test
     */
    public function testAllowMixinOverride()
    {
        $template = '
mixin foo()
  h1 Hello

mixin foo()
  h2 Hello

+foo
';

        $Pug = new Pug(array(
            'singleQuote' => false,
            'allowMixinOverride' => true,
        ));
        $actual = $Pug->render($template);
        $expected = '<h2>Hello</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override enabled');

        $Pug = new Pug(array(
            'singleQuote' => false,
            'allowMixinOverride' => false,
        ));
        $actual = $Pug->render($template);
        $expected = '<h1>Hello</h1>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override disabled');
    }

    /**
     * allowMixinOverride setting test
     */
    public function testRestrictedScope()
    {
        $template = '
mixin foo()
  if isset($bar)
    h1=bar
  else
    h1 Not found
  block

- bar="Hello"

+foo
  if isset($bar)
    h2=bar
  else
    h2 Not found
';

        $Pug = new Pug(array(
            'restrictedScope' => true,
        ));
        $actual = $Pug->render($template);
        $expected = '<h1>Not found</h1><h2>Not found</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Restricted scope enabled');

        $Pug->setOption('restrictedScope', false);
        $actual = $Pug->render($template);
        $expected = '<h1>Hello</h1><h2>Hello</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Restricted scope disabled');
    }

    /**
     * allowMixinOverride setting test with dynamic mixin name
     */
    public function testOverrideDynamicMixin()
    {
        $Pug = new Pug(array(
            'allowMixinOverride' => false,
            'prettyprint' => true,
        ));

        $actual = static::rawHtml($Pug->render(__DIR__ . '/../templates/xml.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/xml.html'));
        $this->assertSame($expected, $actual);

        $actual = static::rawHtml($Pug->render(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        $this->assertSame($expected, $actual);

        $Pug = new Pug(array(
            'allowMixinOverride' => true,
            'prettyprint' => true,
        ));
        $actual = static::rawHtml($Pug->render(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        $this->assertSame($expected, $actual);
    }

    /**
     * singleQuote setting test
     */
    public function testSingleQuote()
    {
        $template = 'h1#foo.bar(style="color: red;") Hello';

        $Pug = new Pug(array(
            'prettyprint' => true,
            'singleQuote' => true,
        ));
        $actual = $Pug->render($template);
        $expected = "<h1 id='foo' style='color: red;' class='bar'>Hello</h1>";

        $this->assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote enabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote enabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote enabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote enabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote enabled on interpolation');

        $Pug = new Pug(array(
            'prettyprint' => true,
            'singleQuote' => false,
        ));
        $actual = $Pug->render($template);
        $expected = '<h1 id="foo" style="color: red;" class="bar">Hello</h1>';

        $this->assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote disabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote disabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote disabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote disabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($Pug->render($file . '.pug')), 'Single quote disabled on interpolation');
    }

    /**
     * phpSingleLine setting test
     */
    public function testPhpSingleLine()
    {
        $template = '
- $foo = "bar"
- $bar = 42
p(class=$foo)=$bar
';

        $Pug = new Pug(array(
            'phpSingleLine' => true,
        ));
        $compile = $Pug->compile($template);
        $actual = substr_count($compile, "\n");
        $expected = substr_count($compile, '<?php') * 2 + 1;

        $this->assertSame($expected, $actual, 'PHP single line enabled');
        $this->assertGreaterThan(5, $actual, 'PHP single line enabled');

        $Pug = new Pug(array(
            'phpSingleLine' => false,
        ));
        $actual = substr_count(trim($Pug->compile($template)), "\n");

        $this->assertLessThan(2, $actual,'PHP single line disabled');
    }

    /**
     * Return HTML if mixed indent is allowed
     */
    public function testAllowMixedIndentEnabled()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => true,
        ));
        $actual = $Pug->render('p' . "\n\t    " . 'i Hi' . "\n    \t" . 'i Ho');
        $expected = '<p><i>Hi</i><i>Ho</i></p>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');

        $actual = $Pug->render('p' . "\n    \t" . 'i Hi' . "\n\t    " . 'i Ho');
        $expected = '<p><i>Hi</i><i>Ho</i></p>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 20
     */
    public function testAllowMixedIndentDisabledTabSpaces()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n\t    " . 'i Hi');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 20
     */
    public function testAllowMixedIndentDisabledSpacesTab()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n    \t" . 'i Hi');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 20
     */
    public function testAllowMixedIndentDisabledSpacesTabAfterSpaces()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n        " . 'i Hi' . "\n    \t" . 'i Hi');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 25
     */
    public function testAllowMixedIndentDisabledSpacesAfterTab()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n\t" . 'i Hi' . "\n    " . 'i Hi');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 25
     */
    public function testAllowMixedIndentDisabledSpacesTextAfterTab()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n\t" . 'i Hi' . "\np.\n    " . 'Hi');
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 25
     */
    public function testAllowMixedIndentDisabledSpacesTabTextAfterTab()
    {
        $Pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $Pug->render('p' . "\n\t\t" . 'i Hi' . "\np\n    \t" . 'i Hi');
    }

    /**
     * Static includeNotFound is deprecated, use the notFound option instead.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 22
     */
    public function testIncludeNotFoundDisabledViaStaticVariable()
    {
        $save = \Pug\Parser::$includeNotFound;
        $Pug = new Pug();
        \Pug\Parser::$includeNotFound = false;

        $error = null;

        try {
            $Pug->render('include does-not-exists');
        } catch (\Exception $e) {
            $error = $e;
        }

        \Pug\Parser::$includeNotFound = $save;

        if ($error) {
            throw $error;
        }
    }

    /**
     * notFound option replace the static variable includeNotFound.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 22
     * @expectedExceptionMessageRegExp /does-not-exists/
     */
    public function testIncludeNotFoundDisabledViaOption()
    {
        $Pug = new Pug(array(
            'notFound' => false
        ));
        $Pug->render('include does-not-exists');
    }

    /**
     * includeNotFound return an error included in content if a file miss.
     */
    public function testIncludeNotFoundEnabledViaStatic()
    {
        $Pug = new Pug();
        $this->assertTrue(!empty(\Pug\Parser::$includeNotFound), 'includeNotFound should be set by default.');

        $actual = $Pug->render('include does-not-exists');
        $notFound = $Pug->render(\Pug\Parser::$includeNotFound);
        $this->assertSame($actual, $notFound, 'A file not found when included should return default includeNotFound value if touched.');

        $save = \Pug\Parser::$includeNotFound;
        \Pug\Parser::$includeNotFound = 'h1 Hello';
        $actual = $Pug->render('include does-not-exists');
        $this->assertSame($actual, '<h1>Hello</h1>', 'A file not found when included should return includeNotFound value if set.');
        \Pug\Parser::$includeNotFound = $save;
    }

    /**
     * notFound option return an error included in content if a file miss.
     */
    public function testIncludeNotFoundEnabledViaOption()
    {
        $Pug = new Pug();
        $actual = $Pug->render('include does-not-exists');
        $notFound = $Pug->render(\Pug\Parser::$includeNotFound);
        $this->assertSame($actual, $notFound, 'A file not found when included should return default includeNotFound value if the notFound option is not set.');

        $Pug = new Pug(array(
            'notFound' => 'p My Not Found Error'
        ));
        $actual = $Pug->render('include does-not-exists');
        $this->assertSame($actual, '<p>My Not Found Error</p>', 'A file not found when included should return notFound value if set.');
    }

    /**
     * indentChar and indentSize allow to configure the indentation.
     */
    public function testIndent()
    {
        $template = '
body
  header
    h1#foo Hello!
  section
    article
      p Bye!';

        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 2,
            'indentChar' => ' ',
        ));
        $actual = str_replace("\r", '', $Pug->render($template));
        $expected = str_replace("\r", '', '<body>
  <header>
    <h1 id="foo">Hello!</h1>
  </header>
  <section>
    <article>
      <p>Bye!</p>
    </article>
  </section>
</body>
');
        $this->assertSame($expected, $actual);

        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 4,
            'indentChar' => ' ',
        ));
        $actual = str_replace("\r", '', $Pug->render($template));
        $expected = str_replace('  ', '    ', $expected);
        $this->assertSame($expected, $actual);

        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 1,
            'indentChar' => "\t",
        ));
        $actual = str_replace("\r", '', $Pug->render($template));
        $expected = str_replace('    ', "\t", $expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * notFound option replace the static variable includeNotFound.
     *
     * @expectedException \ErrorException
     * @expectedExceptionCode 29
     */
    public function testNoBaseDir()
    {
        $Pug = new Pug();
        $Pug->render(__DIR__ . '/../templates/auxiliary/include-sibling.pug');
    }

    public function renderWithBaseDir($basedir, $template)
    {
        $Pug = new Pug(array(
            'prettyprint' => true,
            'basedir' => $basedir,
        ));
        $code = $Pug->render($template);

        return trim(preg_replace('/\n\s+/', "\n", str_replace("\r", '', $code)));
    }

    public function testBaseDir()
    {
        $actual = $this->renderWithBaseDir(
            __DIR__ . '/..',
            __DIR__ . '/../templates/auxiliary/include-sibling.pug'
        );
        $expected = "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n".
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>";
        $this->assertSame($expected, $actual);

        $actual = $this->renderWithBaseDir(
            __DIR__ . '/../templates/',
            __DIR__ . '/../templates/auxiliary/include-sibling.pug'
        );
        $expected = "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n".
            "<p>World</p>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>";
        $this->assertSame($expected, $actual);

        $actual = $this->renderWithBaseDir(
            __DIR__ . '/../templates/auxiliary',
            __DIR__ . '/../templates/auxiliary/include-sibling.pug'
        );
        $expected = "<p>World</p>\n" .
            "<p>World</p>\n".
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>";
        $this->assertSame($expected, $actual);

        $actual = $this->renderWithBaseDir(
            __DIR__ . '/../templates/auxiliary/nothing',
            __DIR__ . '/../templates/auxiliary/include-sibling.pug'
        );
        $expected = "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n".
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>";
        $this->assertSame($expected, $actual);

        $actual = $this->renderWithBaseDir(
            __DIR__ . '/../templates/',
            __DIR__ . '/../templates/auxiliary/include-basedir.pug'
        );
        $expected = "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>";
        $this->assertSame($expected, $actual);

        $actual = $this->renderWithBaseDir(
            __DIR__ . '/../templates/',
            __DIR__ . '/../templates/auxiliary/extends-basedir.pug'
        );
        $expected =
            "<html>\n" .
            "<head>\n" .
            "<title>My Application</title>\n" .
            "</head>\n" .
            "<body>\n" .
            "<h1>test</h1>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "<p>World</p>\n" .
            "<div class=\"alert alert-danger\">Page not found.</div>\n" .
            "<p>World</p>\n" .
            "</body>\n" .
            "</html>";
        $this->assertSame($expected, $actual);
    }

    public function testClassAttribute()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'classAttribute' => 'className',
        ));
        $actual = trim($Pug->render('.foo.bar Hello'));
        $expected = '<div className="foo bar">Hello</div>';
        $this->assertSame($expected, $actual);
    }
}
