<?php

require dirname(__FILE__) . '/../vendor/autoload.php';

class JadePHPTest extends PHPUnit_Framework_TestCase {

    static private $skipped = array(
        // Here is the remain to implement list
        'inheritance.extend.mixins',
        'mixin.attrs',
        'mixin.block-tag-behaviour',
        'mixin.blocks',
        'mixin.merge',
        'tag.interpolation'
    );

    public function caseProvider() {

        $cases = array();

        foreach (build_list(find_tests()) as $arr) {
            foreach ($arr as $e) {
                if ($e['name'] === 'index') {
                    continue;
                }
                $cases[] = array($e['name']);
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
}
