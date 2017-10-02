<?php

use Pug\Pug;

class ParseMethodFilter
{
    public function parse($code)
    {
        return strtolower($code);
    }
}

class SpecialScript extends \Pug\Filter\AbstractFilter
{
    protected $tag = 'script';
}

class PugFilterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @group filters
     * custom filter test
     */
    public function testFilter()
    {
        $pug = new Pug([
            'debug' => true,
        ]);
        $this->assertSame("<?php\nfoo\n?>", call_user_func($pug->getFilter('php'), 'foo'));
        $this->assertFalse($pug->hasFilter('text'));
        $pug->filter('text', function($code) {
            return strip_tags($code);
        });
        $this->assertTrue($pug->hasFilter('text'));
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

        $this->assertSame($expected, $actual, 'Custom filter');
    }

    /**
     * @group filters
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid bar filter given
     */
    public function testNonCallableFilter()
    {
        $pug = new Pug([
            'debug' => true,
        ]);
        $this->assertFalse($pug->hasFilter('bar'));
        $pug->filter('bar', 'nonexists');
    }

    /**
     * @group filters
     */
    public function testFilterAutoload()
    {
        $pug = new Pug([
            'debug' => true,
            'filterAutoLoad' => false,
        ]);
        $this->assertFalse($pug->hasFilter('foo-bar'));
        spl_autoload_register(function ($name) {
            $name = explode('\\', $name);
            $file = __DIR__ . '/../lib/' . end($name) . 'Filter.php';
            if (file_exists($file)) {
                include_once $file;
            }
        });
        $this->assertFalse($pug->hasFilter('foo-bar'));
        $this->assertSame($pug->getFilter('foo-bar'), null);

        $pug = new Pug([
            'debug' => true,
            'filterAutoLoad' => true,
        ]);
        $this->assertTrue($pug->hasFilter('foo-bar'));
        $this->assertSame('I\'M SO TALL :)', call_user_func($pug->getFilter('foo-bar'), 'I\'m so small :('));
        $actual = $pug->render('
div
    p
        :foo-bar
            I\'m so small :(
');
        $expected = '<div><p>I\'M SO TALL :)</p></div>';

        $this->assertSame(preg_replace('`\s`', '', $expected), preg_replace('`\s`', '', $actual), 'Autoloaded filter');
    }

    /**
     * @group filters
     * @expectedException \Phug\CompilerException
     * @expectedExceptionMessage Unknown filter bar-foo
     */
    public function testFilterAutoloadWhenClassDoNotExist()
    {
        $pug = new Pug([
            'debug' => true,
        ]);
        $this->assertFalse($pug->hasFilter('bar-foo'));
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

        $this->assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:lower FOO]-BAR');

        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     */
    public function testParseMethod()
    {
        $pug = new Pug([
            'debug' => true,
        ]);
        $pug->filter('lower', ParseMethodFilter::class);
        $actual = $pug->render('
h1
    | BAR-
    :lower FOO
    | -BAR
');
        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:lower FOO]-BAR');

        $expected = '/^<h1>BAR-\s+foo\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     */
    public function testCData()
    {
        $filter = new \Pug\Filter\Cdata();

        $this->assertSame("<![CDATA[\nfoo\n]]>", $filter('foo'));
    }

    /**
     * @group filters
     */
    public function testParseAutoload()
    {
        include_once __DIR__ . '/../lib/AutoloadParseFilter.php';
        $pug = new Pug();

        $this->assertSame('foobar', $pug->render(':autoload-parse-filter'));
    }

    /**
     * @group filters
     */
    public function testWrapInTag()
    {
        $filter = new SpecialScript();

        $this->assertSame('<script>foo</script>', $filter->pugInvoke('foo'));
    }

    /**
     * @group filters
     * @group php-filter-prettyprint
     */
    public function testPhpFilterWithoutPrettyprint()
    {
        $pug = new Pug([
            'debug' => true,
        ]);
        $actual = $pug->render('
h1
    |BAR-
    :php
        echo 6 * 7
    |-BAR
');
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'Block filter');

        $actual = $pug->render('
h1
    span BAR-
    :php
        echo 6 * 7
    span -BAR
');
        $expected = '<h1><span>BAR-</span>42<span>-BAR</span></h1>';

        $this->assertSame($expected, $actual, 'Block filter and span');

        $actual = $pug->render('
h1
    | BAR-
    :php echo 6 * 7
    | -BAR
');
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'One-line filter');

        $actual = $pug->render('h1 BAR-#[:php echo 6 * 7]-BAR');

        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'In-line filter');
    }

    /**
     * @group filters
     * @group php-filter-prettyprint
     */
    public function testPhpFilterWithPrettyprint()
    {
        $pug = new Pug([
            'debug' => true,
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

        $this->assertRegExp($expected, $actual, 'Block filter');

        $actual = trim($pug->render('
h1
    span BAR-
    :php
        echo 6 * 7
    span -BAR
'));
        $expected = '/^<h1><span>BAR-<\/span>42<span>-BAR<\/span><\/h1>$/';

        $this->assertRegExp($expected, $actual, 'Block filter and span');

        $actual = trim($pug->render('
h1
    | BAR-
    :php echo 6 * 7
    | -BAR
'));
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'One-line filter');

        $actual = trim($pug->render('h1 BAR-#[:php echo 6 * 7]-BAR'));
        $expected = '/^<h1>BAR-\s+42\s+-BAR<\/h1>$/';

        $this->assertRegExp($expected, $actual, 'In-line filter');
    }
}
