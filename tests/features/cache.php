<?php

use Jade\Jade;

class JadeTest extends Jade{

    protected $compilationsCount = 0;

    public function getCompilationsCount()
    {
        return $this->compilationsCount;
    }

    public function compile($input)
    {
        $this->compilationsCount++;
        return parent::compile($input);
    }
}

class JadeCacheTest extends PHPUnit_Framework_TestCase {

    protected function emptyDirectory($dir)
    {
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->emptyDirectory($path);
                } else {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @expectedException ErrorException
     */
    public function testMissingDirectory() {

        $jade = new Jade(array(
            'cache' => 'does/not/exists'
        ));
        $jade->render(__DIR__ . '/../templates/attrs.jade');
    }

    /**
     * Cache from string input
     */
    public function testStringInputCache() {

        $dir = sys_get_temp_dir() . '/jade';
        if (file_exists($dir)) {
            if (is_file($dir)) {
                unlink($dir);
                mkdir($dir);
            } else {
                $this->emptyDirectory($dir);
            }
        } else {
            mkdir($dir);
        }
        $jade = new JadeTest(array(
            'cache' => $dir
        ));
        $this->assertSame(0, $jade->getCompilationsCount(), 'Should have done always 2 compilations because the code changed');
        $this->assertSame(0, $jade->getCompilationsCount(), 'Should have done no compilations yet');
        $jade->render("header\n  h1#foo Hello World!\nfooter");
        $this->assertSame(1, $jade->getCompilationsCount(), 'Should have done 1 compilation');
        $jade->render("header\n  h1#foo Hello World!\nfooter");
        $this->assertSame(1, $jade->getCompilationsCount(), 'Should have done always 1 compilation because the code is cached');
        $jade->render("header\n  h1#foo Hello World?\nfooter");
        $this->assertSame(2, $jade->getCompilationsCount(), 'Should have done always 2 compilations because the code changed');
        $this->emptyDirectory($dir);
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
        $this->assertSame($stream, $jade->stream($jade->compile($file)), 'Should return the stream of attrs.jade.');
        $this->assertSame(mb_substr($stream, mb_strlen($start)), file_get_contents($cachedFile), 'The cached file should contains the same contents.');
        touch($file, time() - 3600);
        $path = $jade->cache($file);
        $this->assertSame($path, $cachedFile, 'The cached file should be used instead if untouched.');
        copy(__DIR__ . '/../templates/mixins.jade', $file);
        touch($file, time() + 3600);
        $stream = $jade->cache($file);
        $this->assertSame($stream, $jade->stream($jade->compile(__DIR__ . '/../templates/mixins.jade')), 'The cached file should be the stream of mixins.jade.');
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
