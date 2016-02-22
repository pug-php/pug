<?php

use Jade\Jade;

class JadeCacheTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException ErrorException
     */
    public function testMissingDirectory() {

        $jade = new Jade(array(
            'cache' => 'does/not/exists'
        ));
        $jade->cache(__DIR__ . '/../templates/attrs.jade');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testMissingFile() {

        $jade = new Jade(array(
            'cache' => sys_get_temp_dir()
        ));
        $jade->cache('not-an-existing-file');
    }
}
