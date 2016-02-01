<?php

class JadeTemplatesTest extends PHPUnit_Framework_TestCase {

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
}
