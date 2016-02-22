<?php

use Jade\Jade;

class JadeSettingsTest extends PHPUnit_Framework_TestCase {

    static private function rawHtml($html) {

        return trim(preg_replace('`\n+`', "\n", strtr(str_replace(array("\r", ' '), '', $html), "'", '"')));
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

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes');

        $jade = new Jade(array(
            'keepNullAttributes' => true,
            'prettyprint' => true,
        ));
        $templates = dirname(__FILE__) . '/../templates/';
        $actual = $jade->render(file_get_contents($templates . 'mixin.attrs.jade'));
        $expected = file_get_contents($templates . 'mixin.attrs-keep-null-attributes.html');

        $this->assertSame(static::rawHtml($actual), static::rawHtml($expected), 'Keep null attributes');
    }
}
