<?php

use Pug\Pug;

class JadeTemplatesTest extends PHPUnit_Framework_TestCase
{
    static private $skipped = array(
        // Not supported in HHVM
        'xml' => 'hhvm',
        'method' => '<7',

        // Add here tests for future features not yet implemented
    );

    public function caseProvider()
    {
        $cases = array();

        foreach (build_list(find_tests()) as $arr) {
            foreach ($arr as $e) {
                $name = $e['name'];

                if ($name === 'index' || in_array($name, self::$skipped)) {
                    continue;
                }
                if (isset(self::$skipped[$name])) {
                    if (defined('HHVM_VERSION') && self::$skipped[$name] === 'hhvm') {
                        continue;
                    }
                    if (version_compare(PHP_VERSION, '7.0.0') < 0 && self::$skipped[$name] === '<7') {
                        continue;
                    }
                }

                $cases[] = array($name);
            }
        }

        return $cases;
    }

    /**
     * @dataProvider caseProvider
     */
    public function testJadeGeneration($name)
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
        $pug = new Pug(array(
            'singleQuote' => false,
            'terse' => true,
        ));

        $html = $pug->render('input(type="checkbox", checked=true)');

        $this->assertSame('<input type="checkbox" checked>', $html, 'Static boolean values should render as simple attributes.');

        $html = $pug->render('input(type="checkbox", checked=isChecked)', array(
            'isChecked' => true,
        ));

        $this->assertSame('<input type="checkbox" checked>', $html, 'Dynamic boolean values should render as simple attributes.');
    }
}
