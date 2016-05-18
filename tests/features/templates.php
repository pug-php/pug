<?php

class JadeTemplatesTest extends PHPUnit_Framework_TestCase {

    static private $skipped = array(
        // Not supported in HHVM
        'xml' => 'hhvm',

        // Add here tests for future features not yet implemented
        'vars.mixins',
        'attrs-data.complex',
    );

    public function caseProvider() {

        $cases = array();

        foreach (build_list(find_tests()) as $arr) {
            foreach ($arr as $e) {
                $name = $e['name'];

                if ($name === 'index' || in_array($name, self::$skipped)) {
                    continue;
                }
                if (defined('HHVM_VERSION') && isset(self::$skipped[$name]) && self::$skipped[$name] === 'hhvm') {
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
}
