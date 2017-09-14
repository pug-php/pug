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
        $pug = new Pug([
            'singleQuote' => false,
            'keepNullAttributes' => false,
            'prettyprint' => true,
        ]);
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $pug->render(file_get_contents($templates . 'mixin.attrs.pug'));
        $expected = file_get_contents($templates . 'mixin.attrs.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes disabled');

        $pug = new Pug([
            'singleQuote' => false,
            'keepNullAttributes' => true,
            'prettyprint' => true,
        ]);
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $pug->render(file_get_contents($templates . 'mixin.attrs.pug'));
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

        $pug = new Pug([
            'singleQuote' => true,
            'prettyprint' => true,
        ]);
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $pug->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($expected, $actual, 'Pretty print enabled');

        $pug = new Pug([
            'singleQuote' => true,
            'prettyprint' => false,
        ]);
        $actual = preg_replace('`[ \t]+`', ' ', $pug->render($template));
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

        $pug = new Pug([
            'singleQuote' => true,
            'prettyprint' => false,
        ]);
        $this->assertFalse($pug->getOption('prettyprint'), 'getOption should return current setting');
        $pug->setOption('prettyprint', true);
        $this->assertTrue($pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $pug->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($actual, $expected, 'Pretty print enabled');

        $pug->setOption('prettyprint', false);
        $this->assertFalse($pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = preg_replace('`[ \t]+`', ' ', $pug->render($template));
        $expected =  '<div id=\'Second\' class=\'centered\'><h1 class=\'foo\'>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($actual, $expected, 'Pretty print disabled');
    }

    /**
     * setOptions test
     */
    public function testSetOptions()
    {
        $pug = new Pug();
        $pug->setOptions(array(
            'prettyprint' => true,
            'cache' => 'abc',
            'indentChar' => '-',
        ));
        $this->assertTrue($pug->getOption('prettyprint'));
        $this->assertSame($pug->getOption('cache'), 'abc');
        $this->assertSame($pug->getOption('indentChar'), '-');
    }

    /**
     * setCustomOption test
     */
    public function testSetCustomOption()
    {
        $pug = new Pug();
        $pug->setCustomOption('i-do-not-exists', 'right');
        $this->assertSame($pug->getOption('i-do-not-exists'), 'right', 'getOption should return custom setting');
    }

    /**
     * setOptions test
     */
    public function testSetCustomOptions()
    {
        $pug = new Pug();
        $pug->setCustomOptions(array(
            'prettyprint' => false,
            'foo' => 'bar',
        ));
        $this->assertFalse($pug->getOption('prettyprint'));
        $this->assertSame($pug->getOption('foo'), 'bar');
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

        $pug = new Pug(array(
            'singleQuote' => false,
            'allowMixinOverride' => true,
        ));
        $actual = $pug->render($template);
        $expected = '<h2>Hello</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override enabled');

        $pug = new Pug(array(
            'singleQuote' => false,
            'allowMixinOverride' => false,
        ));
        $actual = $pug->render($template);
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

        $pug = new Pug(array(
            'restrictedScope' => true,
        ));
        $actual = $pug->render($template);
        $expected = '<h1>Not found</h1><h2>Not found</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Restricted scope enabled');

        $pug->setOption('restrictedScope', false);
        $actual = $pug->render($template);
        $expected = '<h1>Hello</h1><h2>Hello</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Restricted scope disabled');
    }

    /**
     * allowMixinOverride setting test with dynamic mixin name
     */
    public function testOverrideDynamicMixin()
    {
        $pug = new Pug(array(
            'allowMixinOverride' => false,
            'prettyprint' => true,
        ));

        $actual = static::rawHtml($pug->renderFile(__DIR__ . '/../templates/xml.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/xml.html'));
        $this->assertSame($expected, $actual);

        $actual = static::rawHtml($pug->renderFile(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        $this->assertSame($expected, $actual);

        $pug = new Pug(array(
            'allowMixinOverride' => true,
            'prettyprint' => true,
        ));
        $actual = static::rawHtml($pug->renderFile(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        $this->assertSame($expected, $actual);
    }

    /**
     * singleQuote setting test
     */
    public function testSingleQuote()
    {
        $template = 'h1#foo.bar(style="color: red;") Hello';

        $pug = new Pug(array(
            'prettyprint' => true,
            'patterns' => [
                'attribute_pattern'         => " %s='%s'",
                'boolean_attribute_pattern' => " %s='%s'",
            ],
        ));
        $actual = $pug->render($template);
        $expected = "<h1 id='foo' class='bar' style='color: red;'>Hello</h1>";

        $this->assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote enabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on interpolation');

        $pug = new Pug(array(
            'prettyprint' => true,
            'patterns' => [
                'attribute_pattern'         => ' %s="%s"',
                'boolean_attribute_pattern' => ' %s="%s"',
            ],
        ));
        $actual = $pug->render($template);
        $expected = '<h1 id="foo" class="bar" style="color: red;">Hello</h1>';

        $this->assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote disabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        $this->assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on interpolation');
    }

    /**
     * Return HTML if mixed indent is allowed
     */
    public function testAllowMixedIndentEnabled()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => true,
        ));
        $actual = $pug->render('p' . "\n\t    " . 'i Hi' . "\n    \t" . 'i Ho');
        $expected = '<p><i>Hi</i><i>Ho</i></p>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');

        $actual = $pug->render('p' . "\n    \t" . 'i Hi' . "\n\t    " . 'i Ho');
        $expected = '<p><i>Hi</i><i>Ho</i></p>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');
    }

    /**
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Invalid indentation, you can use tabs or spaces but not both
     */
    public function testAllowMixedIndentDisabledTabSpaces()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t    " . 'i Hi');
    }

    /**
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Invalid indentation, you can use tabs or spaces but not both
     */
    public function testAllowMixedIndentDisabledSpacesTab()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n    \t" . 'i Hi');
    }

    /**
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Invalid indentation, you can use tabs or spaces but not both
     */
    public function testAllowMixedIndentDisabledSpacesTabAfterSpaces()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n        " . 'i Hi' . "\n    \t" . 'i Hi');
    }

    /**
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Invalid indentation, you can use tabs or spaces but not both
     */
    public function testAllowMixedIndentDisabledSpacesAfterTab()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t" . 'i Hi' . "\n    " . 'i Hi');
    }

    /**
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Invalid indentation, you can use tabs or spaces but not both
     */
    public function testAllowMixedIndentDisabledSpacesTabTextAfterTab()
    {
        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t\t" . 'i Hi' . "\np\n    \t" . 'i Hi');
    }

    /**
     * notFound option replace the static variable includeNotFound.
     *
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Source file does-not-exists not found
     */
    public function testIncludeNotFoundDisabledViaOption()
    {
        $pug = new Pug();
        $pug->render('include does-not-exists');
    }

    /**
     * includeNotFound return an error included in content if a file miss.
     */
    public function testIncludeNotFoundEnabledViaStatic()
    {
        $pug = new Pug();
        $this->assertTrue(!empty(\Pug\Parser::$includeNotFound), 'includeNotFound should be set by default.');

        $actual = $pug->render('include does-not-exists');
        $notFound = $pug->render(\Pug\Parser::$includeNotFound);
        $this->assertSame($actual, $notFound, 'A file not found when included should return default includeNotFound value if touched.');

        $save = \Pug\Parser::$includeNotFound;
        \Pug\Parser::$includeNotFound = 'h1 Hello';
        $actual = $pug->render('include does-not-exists');
        $this->assertSame($actual, '<h1>Hello</h1>', 'A file not found when included should return includeNotFound value if set.');
        \Pug\Parser::$includeNotFound = $save;
    }

    /**
     * notFound option return an error included in content if a file miss.
     */
    public function testIncludeNotFoundEnabledViaOption()
    {
        $pug = new Pug();
        $actual = $pug->render('include does-not-exists');
        $notFound = $pug->render(\Pug\Parser::$includeNotFound);
        $this->assertSame($actual, $notFound, 'A file not found when included should return default includeNotFound value if the notFound option is not set.');

        $pug = new Pug(array(
            'notFound' => 'p My Not Found Error'
        ));
        $actual = $pug->render('include does-not-exists');
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

        $pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => '  ',
        ));
        $actual = str_replace("\r", '', $pug->render($template));
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

        $pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => '    ',
        ));
        $actual = str_replace("\r", '', $pug->render($template));
        $expected = str_replace('  ', '    ', $expected);
        $this->assertSame($expected, $actual);

        $pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => "\t",
        ));
        $actual = str_replace("\r", '', $pug->render($template));
        $expected = str_replace('    ', "\t", $expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * notFound option replace the static variable includeNotFound.
     *
     * @expectedException \Phug\RendererException
     * @expectedExceptionMessage Either the "basedir" or "paths" option is required
     */
    public function testNoBaseDir()
    {
        $pug = new Pug();
        $pug->renderFile(__DIR__ . '/../templates/auxiliary/include-sibling.pug');
    }

    public function renderWithBaseDir($basedir, $template)
    {
        $pug = new Pug(array(
            'pretty' => true,
            'basedir' => $basedir,
            'not_found_template' => '.alert.alert-danger Page not found.',
        ));
        $code = $pug->renderFile($template);

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
        $pug = new Pug(array(
            'singleQuote' => false,
            'classAttribute' => 'className',
        ));
        $actual = trim($pug->render('.foo.bar Hello'));
        $expected = '<div className="foo bar">Hello</div>';
        $this->assertSame($expected, $actual);
    }
}
