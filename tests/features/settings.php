<?php

use Jade\Jade;

class JadeSettingsTest extends PHPUnit_Framework_TestCase {

    static private function rawHtml($html) {

        return trim(preg_replace('`\n{2,}`', "\n", strtr(str_replace(array("\r", ' '), '', $html), "'", '"')));
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

+centered(\'Section 1\')#Second
  p Some important content.
';

        $jade = new Jade(array(
            'prettyprint' => true,
        ));
        $actual = trim(preg_replace('`[ \t]+`', ' ', preg_replace('`\n( +\n)+`', "\n", str_replace("\r", '', $jade->render($template)))));
        $expected = trim('
 <div id=\'Second\' class=\'centered\'>
 <h1 >
 Section 1 ' . '
 </h1>
<p>
 Some important content.
</p>
 </div>
');

        $this->assertSame($actual, $expected, 'Pretty print enabled');

        $jade = new Jade(array(
            'prettyprint' => false,
        ));
        $actual = preg_replace('`(<[a-z][a-z:_0-9]*) (?=[\s>])`', '$1', $jade->render($template));
        $expected = '<div id=\'Second\' class=\'centered\'><h1>Section 1</h1><p>Some important content.</p></div>';

        $this->assertSame($actual, $expected, 'Pretty print disabled');
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
}
