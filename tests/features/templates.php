<?php

use PHPUnit\Framework\TestCase;
use Pug\Pug;

class PugTemplatesTest extends TestCase
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

        self::assertSame($result[1], $result[2], $name);
    }

    public function testEmptyTemplate()
    {
        $pug = new Pug();

        self::assertSame('', $pug->render(''), 'Empty string should render empty string.');
    }

    public function testVariablesHandle()
    {
        $pug = new Pug([
            'singleQuote' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $html = $pug->render('input(type="checkbox", checked=true)');

        self::assertSame('<input type="checkbox" checked>', $html, 'Static boolean values should render as simple attributes.');

        $html = $pug->render('input(type="checkbox", checked=isChecked)', [
            'isChecked' => true,
        ]);

        self::assertSame('<input type="checkbox" checked>', $html, 'Dynamic boolean values should render as simple attributes.');
    }

    public function testSpacesRender()
    {
        $pug = new Pug([
            'prettyprint' => false,
        ]);

        $html = $pug->render("i a\ni b");

        self::assertSame('<i>a</i><i>b</i>', $html);

        $html = $pug->render("i a\n=' '\ni b");

        self::assertSame('<i>a</i> <i>b</i>', $html);

        $html = str_replace("\n", '', $pug->render("p\n  | #[i a] #[i b]"));

        self::assertSame('<p><i>a</i> <i>b</i></p>', $html);

        $html = $pug->render("p this is#[a(href='#') test]string");

        self::assertSame('<p>this is<a href="#">test</a>string</p>', $html);

        $html = str_replace("\n", '', $pug->render("p this is #[a(href='#') test string]"));

        self::assertSame('<p>this is <a href="#">test string</a></p>', $html);

        $html = $pug->render("p this is #[a(href='#') test] string");

        self::assertSame('<p>this is <a href="#">test</a> string</p>', $html);

        $html = str_replace("\n", '', $pug->render("p this is #[a(href='#') test string]"));

        self::assertSame('<p>this is <a href="#">test string</a></p>', $html);
    }

    public function testRender()
    {
        $dir = getcwd();
        $pug = new Pug();
        chdir(__DIR__ . '/../templates');
        $actual = $pug->render('basic.pug');
        $expected = $pug->render(file_get_contents('basic.pug'));

        self::assertSame($actual, $expected, '->render should fallback to ->renderFile if strict = false.');

        $pug = new Pug(array('strict' => true));
        $actual = $pug->render('basic.pug');
        $expected = '<basic class="pug"></basic>';

        self::assertSame($actual, $expected, '->render should not fallback to ->renderFile if strict = true.');

        chdir($dir);
    }

    /**
     * @group display
     */
    public function testDisplay()
    {
        $dir = getcwd();
        $pug = new Pug();
        chdir(__DIR__ . '/../templates');

        ob_start();
        $pug->display(file_get_contents('basic.pug'));
        $expected = ob_get_contents();
        ob_end_clean();

        ob_start();
        $pug->display('basic.pug');
        $actual = ob_get_contents();
        ob_end_clean();

        self::assertSame($actual, $expected, '->display should fallback to ->displayFile if strict = false.');

        ob_start();
        $pug->displayFile('basic.pug');
        $actual = ob_get_contents();
        ob_end_clean();

        self::assertSame($actual, $expected, '->display should fallback to ->displayFile if strict = false.');

        $pug = new Pug(array('strict' => true));
        ob_start();
        $pug->display('basic.pug');
        $actual = ob_get_contents();
        ob_end_clean();
        $expected = '<basic class="pug"></basic>';

        self::assertSame($actual, $expected, '->display should not fallback to ->displayFile if strict = true.');

        chdir($dir);
    }
}
