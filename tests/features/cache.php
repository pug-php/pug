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

    /**
     * @expectedException ErrorException
     */
    public function testReadOnlyDirectory() {

        $dir = __DIR__;
        while (is_writeable($dir)) {
            $parent = realpath($dir . '/..');
            if ($parent === $dir) {
                $dir = 'C:';
                if (!file_exists($dir) || is_writable($dir)) {
                    throw new \ErrorException("No read-only directory found to do the test", 1);
                }
                break;
            }
            $dir = $parent;
        }
        $jade = new Jade(array(
            'cache' => $dir
        ));
        $jade->cache(__DIR__ . '/../templates/attrs.jade');
    }

    private function cacheSystem($keepBaseName) {

        $file = tempnam(sys_get_temp_dir(), 'jade-test-');
        $jade = new Jade(array(
            'keepBaseName' => $keepBaseName,
            'cache' => sys_get_temp_dir(),
        ));
        copy(__DIR__ . '/../templates/attrs.jade', $file);
        $name = basename($file);
        $cachedFile = sys_get_temp_dir() . '/' . ($keepBaseName ? $name : '') . md5($file) . '.php';
        if (file_exists($cachedFile)) {
            unlink($cachedFile);
        }
        $stream = $jade->cache($file);
        $start = 'jade.stream://data;';
        $this->assertTrue(mb_strpos($stream, $start) === 0, 'Fresh content should be a stream.');
        $this->assertTrue(file_exists($cachedFile), 'The cached file should now exist.');
        $this->assertSame($stream, $jade->stream($file), 'Should return the stream of attrs.jade.');
        $this->assertSame(mb_substr($stream, mb_strlen($start)), file_get_contents($cachedFile), 'The cached file should contains the same contents.');
        touch($file, time() - 3600);
        $path = $jade->cache($file);
        $this->assertSame($path, $cachedFile, 'The cached file should be used instead if untouched.');
        copy(__DIR__ . '/../templates/mixins.jade', $file);
        touch($file, time() + 3600);
        $stream = $jade->cache($file);
        $this->assertSame($stream, $jade->stream(__DIR__ . '/../templates/mixins.jade'), 'The cached file should be the stream of mixins.jade.');
        unlink($file);
    }

    /**
     * Normal function
     */
    public function testCache() {

        $this->cacheSystem(false);
    }

    /**
     * Test option keepBaseName
     */
    public function testCacheWithKeepBaseName() {

        $this->cacheSystem(true);
    }
}
