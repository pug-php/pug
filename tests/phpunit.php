<?php

require __DIR__ . '/../vendor/autoload.php';

class JadePHPTest extends PHPUnit_Framework_TestCase {

    static private $skipped = array(
        // Add here tests for future features not yet implemented
    );

    public function caseProvider() {

        $cases = array();

        foreach (build_list(find_tests()) as $arr) {
            foreach ($arr as $e) {
                $name = $e['name'];

                if ($name === 'index' || in_array($name, self::$skipped)) {
                    continue;
                }
                $cases[] = array($name);
            }
        }

        return $cases;
    }

    /**
     * @dataProvider caseProvider
     */
    public function testJadeGeneration($name) {

        $result = get_test_result($name);
        $result = $result[1];

        $this->assertSame($result[1], $result[2], $name);
    }

    /**
     * @expectedException Exception
     */
    public function testUnexpectingToken() {

        get_php_code('a(href=="a")');
    }

    /**
     * custom filter test
     */
    public function testFilter() {

        $jade = new \Jade\Jade();
        $this->assertFalse($jade->hasFilter('text'));
        $jade->filter('text', function($node, $compiler){
            foreach ($node->block->nodes as $line) {
                $output[] = $compiler->interpolate($line->value);
            }
            return strip_tags(implode(' ', $output));
        });
        $this->assertTrue($jade->hasFilter('text'));
        $actual = $jade->render('
div
    p
        :text
            article <span>foo</span> bar <img title="foo" />
            <div>section</div>
    :text
        <input /> form
        em strong quote code
');
        $expected = '<div><p>article foo bar section</p>form em strong quote code</div>';

        $this->assertSame(str_replace(' ', '', $actual), str_replace(' ', '', $expected), 'Custom filter');
    }
}
