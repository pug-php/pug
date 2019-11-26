<?php

use NodejsPhpFallback\NodejsPhpFallback;
use Pug\Pug;
use Pug\Test\AbstractTestCase;

class PugJsTest extends AbstractTestCase
{
    /**
     * @group pugjs
     */
    public function testPugJsOption()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'pugjs' => true,
        ]);

        $html = $pug->render('h1=name', ['name' => 'Yop']);

        self::assertSame('<h1>Yop</h1>', $html);

        $html = $pug->renderFile(__DIR__ . '/../templates/basic.pug');

        self::assertSame('<html><body><h1>Title</h1></body></html>', $html);

        $pug->setOption('cache', sys_get_temp_dir());
        $name = 'basic-copy-' . mt_rand(0, 99999999);
        $source = sys_get_temp_dir() . '/' . $name . '.pug';
        $cache = sys_get_temp_dir() . '/' . $name . '.js';
        copy(__DIR__ . '/../templates/basic.pug', $source);

        if (file_exists($cache)) {
            unlink($cache);
        }

        $html = trim($pug->renderFile($source));
        clearstatcache();

        self::assertFileExists($cache);

        self::assertSame('<html><body><h1>Title</h1></body></html>', $html);

        file_put_contents($source, 'p=greet');
        touch($source, time() - 10);
        touch($cache, time() + 10);
        clearstatcache();

        $html = trim($pug->renderFile($source, array(
            'greet' => 'Hello'
        )));

        self::assertSame('<html><body><h1>Title</h1></body></html>', $html);

        touch($cache, time() - 20);
        clearstatcache();

        $html = trim($pug->renderFile($source, array(
            'greet' => 'Hello'
        )));

        self::assertSame('<p>Hello</p>', $html);

        $html = trim($pug->renderFile($source, array(
            'greet' => 'Bye'
        )));

        self::assertSame('<p>Bye</p>', $html);

        file_put_contents($source, 'div: p');
        touch($cache, time() - 20);
        clearstatcache();

        $html = trim($pug->renderFile($source));

        self::assertSame('<div><p></p></div>', $html);

        $pug->setOption('prettyprint', true);

        touch($cache, time() - 20);
        clearstatcache();

        $html = trim($pug->renderFile($source));

        self::assertSame("<div>\n  <p></p>\n</div>", $html);

        $pug->setOption('pretty', true);

        touch($cache, time() - 20);
        clearstatcache();

        $html = trim($pug->renderFile($source));

        self::assertSame("<div>\n  <p></p>\n</div>", $html);

        $pug->setOption('prettyprint', true);

        touch($cache, time() - 20);
        clearstatcache();

        $html = trim($pug->renderFile($source));

        self::assertSame("<div>\n  <p></p>\n</div>", $html);

        unlink($source);
        unlink($cache);
    }

    /**
     * @group pugjs
     */
    public function testPugJsDisplay()
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'pugjs' => true,
        ]);

        ob_start();
        $pug->display('h1=name', ['name' => 'Yop']);
        $html = ob_get_contents();
        ob_end_clean();

        self::assertSame('<h1>Yop</h1>', $html);

        ob_start();
        $pug->displayFile(__DIR__ . '/../templates/basic.pug');
        $html = ob_get_contents();
        ob_end_clean();

        self::assertSame('<html><body><h1>Title</h1></body></html>', $html);
    }

    /**
     * @group pugjs
     */
    public function testPugJsBasename()
    {
        sys_get_temp_dir();
        $name = 'basic-copy-' . mt_rand(0, 99999999);
        $source = sys_get_temp_dir() . '/' . $name . '.pug';
        $cache = sys_get_temp_dir() . '/' . $name . '.js';
        copy(__DIR__ . '/../templates/basic.pug', $source);
        chdir(sys_get_temp_dir());
        $pug = new Pug([
            'pugjs' => true,
            'cache' => '.',
        ]);
        $html = trim($pug->renderFile($name . '.pug'));

        self::assertSame('<html><body><h1>Title</h1></body></html>', $html);

        unlink($source);
        unlink($cache);
    }

    /**
     * @group pugjs
     */
    public function testPugJsOptionException()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('is not a valid class name');

        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'pugjs' => true,
        ]);

        $pug->render('./\ç@');
    }

    /**
     * @group pugjs
     */
    public function testPugJsNodePath()
    {
        $pug = new Pug([
            'nodePath' => 'bin/node',
        ]);

        self::assertSame('bin/node', $pug->getNodeEngine()->getNodePath());
    }

    /**
     * @group pugjs
     */
    public function testIssue147()
    {
        $pug = new Pug(array(
            'pugjs' => true,
        ));

        $html = $pug->render(
            'link(rel="shortcut icon", href=site.favicon, type="image/png")',
            array('site' => array('favicon' => '/favicon.png'))
        );

        self::assertSame('<link rel="shortcut icon" href="/favicon.png" type="image/png"/>', $html);
    }

    /**
     * @group pugjs
     */
    public function testLocalsJsonFile()
    {
        $pug = new Pug(array(
            'pugjs' => true,
            'localsJsonFile' => true
        ));

        $html = $pug->render(
            'link(rel="shortcut icon", href=site.favicon, type="image/png")',
            array('site' => array('favicon' => '/favicon.png'))
        );

        self::assertSame('<link rel="shortcut icon" href="/favicon.png" type="image/png"/>', $html);
    }

    /**
     * @group pugjs
     */
    public function testRenderWithoutFilename()
    {
        $pug = new Pug(array(
            'pugjs' => true,
            'localsJsonFile' => true
        ));

        $html = $pug->renderWithJs(
            'link(rel="shortcut icon", href=site.favicon, type="image/png")',
            array('site' => array('favicon' => '/favicon.png')),
            function () {}
        );

        self::assertSame('<link rel="shortcut icon" href="/favicon.png" type="image/png"/>', $html);
    }

    /**
     * @group pugjs
     */
    public function testLibraryInTemplates()
    {
        NodejsPhpFallback::installPackages(['moment']);

        $pug = new Pug(array(
            'pugjs' => true,
        ));

        $html = $pug->render("- moment = require('moment')\np=moment('12-25-1995', 'MM-DD-YYYY').format('DD-MM-YYYY')");

        self::assertSame('<p>25-12-1995</p>', $html);
    }
}
