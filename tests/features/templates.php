<?php

use Pug\Pug;

class PugTemplatesTest extends PHPUnit_Framework_TestCase
{
    public function caseProvider()
    {
        $cases = [];

        foreach (build_list(find_tests()) as $arr) {
            foreach ($arr as $e) {
                $name = $e['name'];

                if ($name === 'index') {
                    continue;
                }

                $cases[] = [$name];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider caseProvider
     */
    public function testPugGeneration($name)
    {
        $result = get_test_result($name);
        $result = $result[1];

        $this->assertSame($result[1], $result[2], $name);
    }

    public function testEmptyTemplate()
    {
        $pug = new Pug();

        $this->assertSame('', $pug->render(''), 'Empty string should render empty string.');
    }

    public function testVariablesHandle()
    {
        $a = null;
        $a['name'] = 'foo';
        $pug = new Pug([
            'singleQuote' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $html = $pug->render('input(type="checkbox", checked=true)');

        $this->assertSame('<input type="checkbox" checked>', $html, 'Static boolean values should render as simple attributes.');

        $html = $pug->render('input(type="checkbox", checked=isChecked)', [
            'isChecked' => true,
        ]);

        $this->assertSame('<input type="checkbox" checked>', $html, 'Dynamic boolean values should render as simple attributes.');
    }

    public function testSpacesRender()
    {
        $pug = new Pug([
            'prettyprint' => false,
        ]);

        $html = $pug->render("i a\ni b");

        $this->assertSame('<i>a</i><i>b</i>', $html);

        $html = $pug->render("i a\n=' '\ni b");

        $this->assertSame('<i>a</i> <i>b</i>', $html);

        $html = str_replace("\n", '', $pug->render("p\n  | #[i a] #[i b]"));

        $this->assertSame('<p><i>a</i> <i>b</i></p>', $html);

        $html = $pug->render("p this is#[a(href='#') test]string");

        $this->assertSame('<p>this is<a href="#">test</a>string</p>', $html);

        $html = str_replace("\n", '', $pug->render("p this is #[a(href='#') test string]"));

        $this->assertSame('<p>this is <a href="#">test string</a></p>', $html);

        $html = $pug->render("p this is #[a(href='#') test] string");

        $this->assertSame('<p>this is <a href="#">test</a> string</p>', $html);

        $html = str_replace("\n", '', $pug->render("p this is #[a(href='#') test string]"));

        $this->assertSame('<p>this is <a href="#">test string</a></p>', $html);
    }
}
