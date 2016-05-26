<?php

use Jade\Jade;

class JadeSettingsTest extends PHPUnit_Framework_TestCase {

    static private function rawHtml($html, $convertSingleQuote = true) {

        $html = str_replace(array("\r", ' '), '', $html);
        if ($convertSingleQuote) {
            $html = strtr($html, "'", '"');
        }
        return trim(preg_replace('`\n{2,}`', "\n", $html));
    }

    /**
     * keepNullAttributes setting test
     */
    public function testKeepNullAttributes() {

        $jade = new Jade(array(
            'keepNullAttributes' => false,
            'prettyprint' => true,
        ));
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $jade->render(file_get_contents($templates . 'mixin.attrs.jade'));
        $expected = file_get_contents($templates . 'mixin.attrs.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes disabled');

        $jade = new Jade(array(
            'keepNullAttributes' => true,
            'prettyprint' => true,
        ));
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $jade->render(file_get_contents($templates . 'mixin.attrs.jade'));
        $expected = file_get_contents($templates . 'mixin.attrs-keep-null-attributes.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes enabled');
    }

    /**
     * prettyprint setting test
     */
    public function testPrettyprint() {

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

        $jade = new Jade(array(
            'prettyprint' => true,
        ));
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $jade->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($expected, $actual, 'Pretty print enabled');

        $jade = new Jade(array(
            'prettyprint' => false,
        ));
        $actual = preg_replace('`[ \t]+`', ' ', $jade->render($template));
        $expected =  '<div id=\'Second\' class=\'centered\'><h1 class=\'foo\'>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($expected, $actual, 'Pretty print disabled');
    }

    /**
     * setOption test
     */
    public function testSetOption() {

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

        $jade = new Jade(array(
            'prettyprint' => false,
        ));
        $this->assertFalse($jade->getOption('prettyprint'), 'getOption should return current setting');
        $jade->setOption('prettyprint', true);
        $this->assertTrue($jade->getOption('prettyprint'), 'getOption should return current setting');
        $actual = trim(preg_replace('`\n[\s\n]+`', "\n", str_replace("\r", '', preg_replace('`[ \t]+`', ' ', $jade->render($template)))));
        $expected = str_replace("\r", '', '<div id=\'Second\' class=\'centered\'>
<h1 class=\'foo\'>Section 1</h1>
<p>Some important content.</p>
</div>');

        $this->assertSame($actual, $expected, 'Pretty print enabled');

        $jade->setOption('prettyprint', false);
        $this->assertFalse($jade->getOption('prettyprint'), 'getOption should return current setting');
        $actual = preg_replace('`[ \t]+`', ' ', $jade->render($template));
        $expected =  '<div id=\'Second\' class=\'centered\'><h1 class=\'foo\'>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($actual, $expected, 'Pretty print disabled');
    }

    /**
     * setOptions test
     */
    public function testSetOptions() {

        $jade = new Jade();
        $jade->setOptions(array(
            'prettyprint' => true,
            'cache' => 'abc',
            'indentChar' => '-',
        ));
        $this->assertTrue($jade->getOption('prettyprint'));
        $this->assertSame($jade->getOption('cache'), 'abc');
        $this->assertSame($jade->getOption('indentChar'), '-');
    }

    /**
     * setCustomOption test
     */
    public function testSetCustomOption() {

        $jade = new Jade();
        $jade->setCustomOption('i-do-not-exists', 'right');
        $this->assertSame($jade->getOption('i-do-not-exists'), 'right', 'getOption should return custom setting');
    }

    /**
     * setOptions test
     */
    public function testSetCustomOptions() {

        $jade = new Jade();
        $jade->setCustomOptions(array(
            'prettyprint' => false,
            'foo' => 'bar',
        ));
        $this->assertFalse($jade->getOption('prettyprint'));
        $this->assertSame($jade->getOption('foo'), 'bar');
    }

    /**
     * allowMixinOverride setting test
     */
    public function testAllowMixinOverride() {

        $template = '
mixin foo()
  h1 Hello

mixin foo()
  h2 Hello

+foo
';

        $jade = new Jade(array(
            'allowMixinOverride' => true,
        ));
        $actual = $jade->render($template);
        $expected = '<h2>Hello</h2>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override enabled');

        $jade = new Jade(array(
            'allowMixinOverride' => false,
        ));
        $actual = $jade->render($template);
        $expected = '<h1>Hello</h1>';

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Allow mixin override disabled');
    }

    /**
     * singleQuote setting test
     */
    public function testSingleQuote() {

        $template = 'h1#foo.bar(style="color: red;") Hello';

        $jade = new Jade(array(
            'singleQuote' => true,
        ));
        $actual = $jade->render($template);
        $expected = "<h1 id='foo' style='color: red;' class='bar'>Hello</h1>";

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Single quote enabled');

        $jade = new Jade(array(
            'singleQuote' => false,
        ));
        $actual = $jade->render($template);
        $expected = '<h1 id="foo" style="color: red;" class="bar">Hello</h1>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Single quote disabled');
    }

    /**
     * phpSingleLine setting test
     */
    public function testPhpSingleLine() {

        $template = '
- $foo = "bar"
- $bar = 42
p(class=$foo)=$bar
';

        $jade = new Jade(array(
            'phpSingleLine' => true,
        ));
        $compile = $jade->compile($template);
        $actual = substr_count($compile, "\n");
        $expected = substr_count($compile, '<?php') * 2;

        $this->assertSame($expected, $actual, 'PHP single line enabled');
        $this->assertGreaterThan(5, $actual, 'PHP single line enabled');

        $jade = new Jade(array(
            'phpSingleLine' => false,
        ));
        $actual = substr_count(trim($jade->compile($template)), "\n");

        $this->assertEquals(0, $actual,'PHP single line disabled');
    }

    /**
     * Return HTML if mixed indent is allowed
     */
    public function testAllowMixedIndentEnabled() {

        $jade = new Jade(array(
            'allowMixedIndent' => true,
        ));
        $actual = $jade->render('p' . "\n\t    " . 'i Hi');
        $expected = '<p><i>Hi</i></p>';

        $this->assertSame(static::rawHtml($actual, false), static::rawHtml($expected, false), 'Allow mixed indent enabled');
    }

    /**
     * @expectedException Exception
     */
    public function testAllowMixedIndentDisabled() {

        $jade = new Jade(array(
            'allowMixedIndent' => false,
        ));

        $jade->render('p' . "\n\t    " . 'i Hi');
    }

    /**
     * @expectedException Exception
     */
    public function testIncludeNotFoundDisabled() {

        $save = \Jade\Parser::$includeNotFound;
        $jade = new Jade();
        \Jade\Parser::$includeNotFound = false;

        $error = null;

        try {
            $actual = $jade->render('include does-not-exists');
        } catch (\Exception $e) {
            $error = $e;
        }

        \Jade\Parser::$includeNotFound = $save;

        if ($error) {
            throw $error;
        }
    }

    /**
     * includeNotFound return a error included if a file miss.
     */
    public function testIncludeNotFoundEnabled() {

        $jade = new Jade();
        $this->assertTrue(!empty(\Jade\Parser::$includeNotFound), 'includeNotFound should be set by default.');

        $actual = $jade->render('include does-not-exists');
        $notFound = $jade->render(\Jade\Parser::$includeNotFound);
        $this->assertSame($actual, $notFound, 'A file not found when included should return includeNotFound value if set.');

        $save = \Jade\Parser::$includeNotFound;
        \Jade\Parser::$includeNotFound = 'h1 Hello';
        $actual = $jade->render('include does-not-exists');
        $this->assertSame($actual, '<h1>Hello</h1>', 'A file not found when included should return includeNotFound value if set.');
        \Jade\Parser::$includeNotFound = $save;
    }

    /**
     * indentChar and indentSize allow to configure the indentation.
     */
    public function testIndent() {

        $template = '
body
  header
    h1#foo Hello!
  section
    article
      p Bye!';

        $jade = new Jade(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 2,
            'indentChar' => ' ',
        ));
        $actual = str_replace("\r", '', $jade->render($template));
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

        $jade = new Jade(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 4,
            'indentChar' => ' ',
        ));
        $actual = str_replace("\r", '', $jade->render($template));
        $expected = str_replace('  ', '    ', $expected);
        $this->assertSame($expected, $actual);

        $jade = new Jade(array(
            'singleQuote' => false,
            'prettyprint' => true,
            'indentSize' => 1,
            'indentChar' => "\t",
        ));
        $actual = str_replace("\r", '', $jade->render($template));
        $expected = str_replace('    ', "\t", $expected);
        $this->assertSame($expected, $actual);
    }
}
