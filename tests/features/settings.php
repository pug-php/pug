<?php

use Phug\CompilerException;
use Phug\LexerException;
use Phug\Phug;
use Pug\Pug;
use Pug\Test\AbstractTestCase;

include_once __DIR__.'/../lib/escape.php';

class PugSettingsTest extends AbstractTestCase
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
            'prettyprint' => true,
        ]);
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $pug->render($template)))));
        $expected = str_replace("\r", '', '<div class="centered" id="Second">
<h1 class="foo">Section 1</h1>
<p>Some important content.</p>
</div>');

        self::assertSame($expected, $actual, 'Pretty print enabled');

        $pug = new Pug([
            'prettyprint' => false,
        ]);
        $actual = preg_replace('`[ \t]+`', ' ', $pug->render($template));
        $expected =  '<div class="centered" id="Second"><h1 class="foo">Section 1</h1><p>Some important content.</p></div>';

        self::assertSame($expected, $actual, 'Pretty print disabled');
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
            'prettyprint' => false,
        ]);
        self::assertFalse($pug->getOption('prettyprint'), 'getOption should return current setting');
        $pug->setOption('prettyprint', true);
        self::assertTrue($pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $pug->render($template)))));
        $expected = str_replace("\r", '', '<div class="centered" id="Second">
<h1 class="foo">Section 1</h1>
<p>Some important content.</p>
</div>');

        self::assertSame($expected, $actual, 'Pretty print enabled');

        $pug->setOption('prettyprint', false);
        self::assertFalse($pug->getOption('prettyprint'), 'getOption should return current setting');
        $actual = preg_replace('`[ \t]+`', ' ', $pug->render($template));
        $expected =  '<div class="centered" id="Second"><h1 class="foo">Section 1</h1><p>Some important content.</p></div>';

        self::assertSame($expected, $actual, 'Pretty print disabled');
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
        self::assertTrue($pug->getOption('prettyprint'));
        self::assertSame('abc', $pug->getOption('cache'));
        self::assertSame('-', $pug->getOption('indentChar'));
        $pug->setOption('cache', 'def');
        self::assertSame('def', $pug->getOption(['cache']));
        $pug->setOption('indentChar', '_');
        self::assertSame('_', $pug->getOption(['indentChar']));
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
            'allowMixinOverride' => true,
        ));
        $actual = $pug->render($template);
        $expected = '<h2>Hello</h2>';

        self::assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override enabled');

        $pug = new Pug(array(
            'allowMixinOverride' => false,
        ));
        $actual = $pug->render($template);
        $expected = '<h1>Hello</h1>';

        self::assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override disabled');
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
        self::assertSame($expected, $actual);

        $actual = static::rawHtml($pug->renderFile(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        self::assertSame($expected, $actual);

        $pug = new Pug(array(
            'allowMixinOverride' => true,
            'prettyprint' => true,
        ));
        $actual = static::rawHtml($pug->renderFile(__DIR__ . '/../templates/mixins.dynamic.pug'));
        $expected = static::rawHtml(file_get_contents(__DIR__ . '/../templates/mixins.dynamic.html'));
        self::assertSame($expected, $actual);
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
                'html_expression_escape'    => 'htmlspecialchars(%s, ENT_QUOTES)',
                'html_text_escape'          => '__escape',
            ],
        ));
        $actual = $pug->render($template);
        $expected = "<h1 id='foo' class='bar' style='color: red;'>Hello</h1>";

        self::assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote enabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.single-quote.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote enabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        self::assertSame(
            str_replace("\n", '', static::simpleHtml(file_get_contents($file . '.single-quote.html'))),
            str_replace("\n", '', static::simpleHtml($pug->renderFile($file . '.pug'))),
            'Single quote enabled on interpolation'
        );

        $pug = new Pug(array(
            'prettyprint' => true,
            'patterns' => [
                'attribute_pattern'         => ' %s="%s"',
                'boolean_attribute_pattern' => ' %s="%s"',
            ],
        ));
        $actual = $pug->render($template);
        $expected = '<h1 id="foo" class="bar" style="color: red;">Hello</h1>';

        self::assertSame(static::rawHtml($expected, false), static::rawHtml($actual, false), 'Single quote disabled on a simple header');
        $file = __DIR__ . '/../templates/attrs-data.complex';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on attrs-data.complex');
        $file = __DIR__ . '/../templates/attrs-data';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on attrs-data');
        $file = __DIR__ . '/../templates/object-to-css';
        self::assertSame(static::simpleHtml(file_get_contents($file . '.html')), static::simpleHtml($pug->renderFile($file . '.pug')), 'Single quote disabled on object-to-css');
        $file = __DIR__ . '/../templates/interpolation';
        self::assertSame(
            str_replace("\n", '', static::simpleHtml(static::simpleHtml(file_get_contents($file . '.html')))),
            str_replace("\n", '', static::simpleHtml($pug->renderFile($file . '.pug'))),
            'Single quote disabled on interpolation'
        );
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

        self::assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');

        $actual = $pug->render('p' . "\n    \t" . 'i Hi' . "\n\t    " . 'i Ho');
        $expected = '<p><i>Hi</i><i>Ho</i></p>';

        self::assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');
    }

    public function testAllowMixedIndentDisabledTabSpaces()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Invalid indentation, you can use tabs or spaces but not both');

        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t    " . 'i Hi');
    }

    public function testAllowMixedIndentDisabledSpacesTab()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Invalid indentation, you can use tabs or spaces but not both');

        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n    \t" . 'i Hi');
    }

    public function testAllowMixedIndentDisabledSpacesTabAfterSpaces()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Invalid indentation, you can use tabs or spaces but not both');

        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n        " . 'i Hi' . "\n    \t" . 'i Hi');
    }

    public function testAllowMixedIndentDisabledSpacesAfterTab()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Invalid indentation, you can use tabs or spaces but not both');

        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t" . 'i Hi' . "\n    " . 'i Hi');
    }

    public function testAllowMixedIndentDisabledSpacesTabTextAfterTab()
    {
        self::expectException(LexerException::class);
        self::expectExceptionMessage('Invalid indentation, you can use tabs or spaces but not both');

        $pug = new Pug(array(
            'allowMixedIndent' => false,
        ));

        $pug->render('p' . "\n\t    " . 'i Hi' . "\np\n    \t" . 'i Hi');
    }

    /**
     * notFound option replace the static variable includeNotFound.
     */
    public function testIncludeNotFoundDisabledViaOption()
    {
        self::expectException(CompilerException::class);
        self::expectExceptionMessage('Source file does-not-exists not found');

        $pug = new Pug();
        $pug->render('include does-not-exists');
    }

    /**
     * notFound option return an error included in content if a file miss.
     */
    public function testIncludeNotFoundEnabledViaOption()
    {
        $pug = new Pug(array(
            'notFound' => 'p My Not Found Error'
        ));
        $actual = $pug->render('include does-not-exists');
        self::assertSame($actual, '<p>My Not Found Error</p>', 'A file not found when included should return notFound value if set.');
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
        self::assertSame($expected, $actual);

        $pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => '    ',
        ));
        $actual = str_replace("\r", '', $pug->render($template));
        $expected = str_replace('  ', '    ', $expected);
        self::assertSame($expected, $actual);

        $pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => "\t",
        ));
        $actual = str_replace("\r", '', $pug->render($template));
        $expected = str_replace('    ', "\t", $expected);
        self::assertSame($expected, $actual);
    }

    /**
     * notFound option replace the static variable includeNotFound.
     */
    public function testNoBaseDir()
    {
        self::expectException(CompilerException::class);
        self::expectExceptionMessage('Either the "basedir" or "paths" option is required');

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
        self::assertSame($expected, $actual);

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
        self::assertSame($expected, $actual);

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
        self::assertSame($expected, $actual);

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
        self::assertSame($expected, $actual);

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
        self::assertSame($expected, $actual);

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
        self::assertSame($expected, $actual);
    }

    public function testClassAttribute()
    {
        $pug = new Pug(array(
            'singleQuote' => false,
            'classAttribute' => 'className',
        ));
        $actual = trim($pug->render('.foo.bar(a="b") Hello'));
        $expected = '<div className="foo bar" a="b">Hello</div>';
        self::assertSame($expected, $actual);
    }

    public function testCustomOptions()
    {
        $pug = new Pug();
        $copy = $pug
            ->setCustomOptions([
                'foo' => 'bar',
                'bar' => 'baz',
            ])
            ->setCustomOption('biz', 'foz');
        self::assertSame($copy, $pug);
        self::assertSame('bar', $pug->getOption('foo'));
        self::assertSame('baz', $pug->getOption('bar'));
        self::assertSame('foz', $pug->getOption('biz'));
    }

    public function testInit()
    {
        Pug::init();

        self::assertInstanceOf(Pug::class, Phug::getRenderer());
    }

    public function testAliases()
    {
        $pug = new Pug([
            'prettyprint' => true,
        ]);

        self::assertTrue($pug->hasOption('prettyprint'));
        self::assertTrue($pug->hasOption('pretty'));

        $pug = new Pug();

        self::assertFalse($pug->hasOption('pretty'));

        $pug = new Pug([
            'pretty' => true,
        ]);

        self::assertTrue($pug->hasOption('prettyprint'));
        self::assertTrue($pug->hasOption('pretty'));
    }
}
