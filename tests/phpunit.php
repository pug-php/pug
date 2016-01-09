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
        static $rawResults = null;
        if(is_null($rawResults)) {
            $rawResults = get_tests_results();
            $rawResults = $rawResults['results'];
        }
        return $rawResults;
    }

    /**
     * @dataProvider caseProvider
     */
    public function testStringGeneration($name, $input, $expected) {
        if(! in_array($name, static::$skipped)) {
            $this->assertSame($input, $expected, $name);
        }
    }
}
