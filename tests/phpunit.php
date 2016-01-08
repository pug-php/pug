<?php

require dirname(__FILE__) . '/../vendor/autoload.php';

class JadePHPTest extends PHPUnit_Framework_TestCase {

    public function testCaseProvider() {
        static $rawResults = null;
        if(is_null($rawResults)) {
            $rawResults = get_tests_results();
            $rawResults = $rawResults['results'];
        }
        return $rawResults;
    }

    /**
     * @dataProvider testCaseProvider
     */
    public function testStringGeneration($input, $expected) {
        $this->assertSame($input, $expected);
    }
}
