<?php

use Phug\CompilerException;
use Pug\Filter\AbstractFilter;
use Pug\Pug;
use Pug\Test\AbstractTestCase;

class ParseMethodFilter
{
    public function parse($code)
    {
        return strtolower($code);
    }
}

class SpecialScript extends AbstractFilter
{
    protected $tag = 'script';
}

class PugFilterTest extends AbstractTestCase
{
    /**
     * @group filters
     * custom filter test
     */
    public function testFilter()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        self::assertSame("<?php\nfoo\n?>", call_user_func($pug->getFilter('php'), 'foo'));
        self::assertFalse($pug->hasFilter('text'));
        $pug->filter('text', function($code) {
            return strip_tags($code);
        });
        self::assertTrue($pug->hasFilter('text'));
        $actual = $pug->render('
div
    p
        :text
            article <span>foo</span> bar <img title="foo" />
            <div>section</div>
    :text
        <input /> form
        em strong quote code
');
        $expected = "<div><p>article foo bar section</p>form em strong quote code</div>";
        $expected = str_replace(' ', '', $expected);
        $actual = str_replace([' ', "\n"], '', $actual);

        self::assertSame($expected, $actual, 'Custom filter');
    }

    /**
     * @group filters
     */
    public function testNonCallableFilter()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid bar filter given');

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        self::assertFalse($pug->hasFilter('bar'));
        $pug->filter('bar', 'nonexists');
    }

    /**
     * @group filters
     */
    public function testFilterAutoload()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'filterAutoLoad' => false,
        ]);
        self::assertFalse($pug->hasFilter('foo-bar'));
        spl_autoload_register(function ($name) {
            $name = explode('\\', $name);
            $file = __DIR__ . '/../lib/' . end($name) . 'Filter.php';
            if (file_exists($file)) {
                include_once $file;
            }
        });
        self::assertFalse($pug->hasFilter('foo-bar'));
        self::assertSame($pug->getFilter('foo-bar'), null);

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'filterAutoLoad' => true,
        ]);
        self::assertTrue($pug->hasFilter('foo-bar'));
        self::assertSame('I\'M SO TALL :)', call_user_func($pug->getFilter('foo-bar'), 'I\'m so small :('));
        $actual = $pug->render('
div
    p
        :foo-bar
            I\'m so small :(
');
        $expected = '<div><p>I\'M SO TALL :)</p></div>';

        self::assertSame(preg_replace('`\s`', '', $expected), preg_replace('`\s`', '', $actual), 'Autoloaded filter');
    }

    /**
     * @group filters
     */
    public function testFilterAutoloadWhenClassDoNotExist()
    {
        self::expectException(CompilerException::class);
        self::expectExceptionMessage('Unknown filter bar-foo');

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        self::assertFalse($pug->hasFilter('bar-foo'));
        $pug->render('
div
    p
        :bar-foo
            article <span>foo</span> bar <img title="foo" />
            <div>section</div>
');
    }

    /**
     * @group filters
     */
    public function testInlineFilter()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        $pug->filter('lower', function($code){
            return strtolower($code);
        });
        $actual = $pug->render('
h1
    | BAR-
    :lower FOO
    | -BAR
');
        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:lower FOO]-BAR');

        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     */
    public function testParseMethod()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        $pug->filter('lower', ParseMethodFilter::class);
        $actual = $pug->render('
h1
    | BAR-
    :lower FOO
    | -BAR
');
        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:lower FOO]-BAR');

        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     */
    public function testCData()
    {
        $filter = new \Pug\Filter\Cdata();

        self::assertSame("<![CDATA[\nfoo\n]]>", $filter('foo'));
    }

    /**
     * @group filters
     */
    public function testParseAutoload()
    {
        include_once __DIR__ . '/../lib/AutoloadParseFilter.php';
        $pug = new Pug();

        self::assertSame('foobar', $pug->render(':autoload-parse-filter'));
    }

    /**
     * @group filters
     */
    public function testWrapInTag()
    {
        $filter = new SpecialScript();

        self::assertSame('<script>foo</script>', $filter->pugInvoke('foo'));
    }

    /**
     * @group filters
     * @group php-filter-prettyprint
     */
    public function testPhpFilterWithoutPrettyprint()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
        ]);
        $actual = $pug->render('
h1
    |BAR-
    :php
        echo 6 * 7
    |-BAR
');
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'Block filter');

        $actual = $pug->render('
h1
    span BAR-
    :php
        echo 6 * 7
    span -BAR
');
        $expected = '<h1><span>BAR-</span>42<span>-BAR</span></h1>';

        self::assertSame($expected, $actual, 'Block filter and span');

        $actual = $pug->render('
h1
    | BAR-
    :php echo 6 * 7
    | -BAR
');
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:php echo 6 * 7]-BAR');

        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     * @group php-filter-prettyprint
     */
    public function testPhpFilterWithPrettyprint()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'prettyprint' => true,
        ]);
        $actual = trim($pug->render('
h1
    | BAR-
    :php
        echo 6 * 7
    | -BAR
'));
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'Block filter');

        $actual = trim($pug->render('
h1
    span BAR-
    :php
        echo 6 * 7
    span -BAR
'));
        $expected = '/^<h1><span>BAR-<\/span>42<span>-BAR<\/span><\/h1>$/';

        self::assertRegExp($expected, $actual, 'Block filter and span');

        $actual = trim($pug->render('
h1
    | BAR-
    :php echo 6 * 7
    | -BAR
'));
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'One-line filter');

        $actual = trim($pug->render('h1 BAR-#[:php echo 6 * 7]-BAR'));
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        self::assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     */
    public function testJsTransformerFilter()
    {
        \NodejsPhpFallback\NodejsPhpFallback::installPackages(['jstransformer-scss']);
        $pug = new Pug();
        $actual = trim($pug->render('
style
    :scss
        #news {
            a {
                font-weight: bold;
            }
        }
'));
        $expected = '/^<style>\s*#news a\s*\{\s*font-weight:\s*bold;\s*\}\s*<\/style>\s*$/';

        self::assertRegExp($expected, $actual, 'Filter using jstransformer-scss');
    }
}
