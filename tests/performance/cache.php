<?php

use Phug\Cli;
use Pug\Optimizer;
use Pug\Pug;
use Pug\Test\AbstractTestCase;

class PugCachePerformanceTest extends AbstractTestCase
{
    protected function removeDirectory($directory)
    {
        foreach (scandir($directory) as $entity) {
            if ($entity === '.' || $entity === '..') {
                continue;
            }

            if (is_dir($directory.'/'.$entity)) {
                $this->removeDirectory($directory.'/'.$entity);
                continue;
            }

            @unlink($directory.'/'.$entity);
        }

        return @rmdir($directory);
    }

    protected function getPerformanceTemplate($template)
    {
        return TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . 'performance' . DIRECTORY_SEPARATOR . $template . '.pug';
    }

    protected function getPhpFromTemplate($template)
    {
        return $this->getPhp(file_get_contents($this->getPerformanceTemplate($template)));
    }

    protected function getPhp($template)
    {
        $pug = new Pug([
            'debug' => false,
            'singleQuote' => false,
            'prettyprint' => false,
            'restrictedScope' => true,
        ]);

        return trim($pug->compile($template));
    }

    protected function getHtmlFromTemplate($template, array $vars = array())
    {
        $pug = new Pug([
            'debug' => true,
            'exit_on_error' => false,
            'singleQuote' => false,
            'prettyprint' => false,
            'restrictedScope' => true,
        ]);

        return trim($pug->render($this->getPerformanceTemplate($template), $vars));
    }

    /**
     * Cache weight.
     */
    public function testCacheWeight()
    {
        self::assertSame('<p>Hello world!</p>', $this->getPhp('p Hello world!'), 'Simple template should output simple code.');
    }

    /**
     * @group cli
     */
    public function testCliAndOptimizerTogether()
    {
        // Load the pug CLI command (will be ./vendor/bin/pug in real life).
        $command = escapeshellarg(realpath(__DIR__.'/../../pug'));

        // Working directory has a views directory and a cache directory.
        $baseDir = sys_get_temp_dir() . '/pug-' . mt_rand(0, 9999999);
        mkdir($baseDir, 0777, true);
        chdir($baseDir);
        mkdir('views', 0777, true);
        mkdir('cache', 0777, true);

        // Write a view sample with an include.
        file_put_contents('views/index.pug', "p=foo\ninclude inc.pug");
        file_put_contents('views/inc.pug', 'h1=bar');

        // Use cache option and no up_to_date_check
        $options = [
            'up_to_date_check' => false,
            'debug'            => false,
            'cache_dir'        => 'cache',
            'paths'            => ['views'],
        ];

        // On deploy, run ./vendor/bin/pug compile-directory views cache '{...}'
        // views and cache are the paths to the directories
        // {...} should be the same options you will use at runtime stringified as JSON
        $json = json_encode($options);

        exec("php $command compile-directory views cache '$json'", $cliOutput, $cliReturn);

        ob_start();

        $cli = new Cli('Pug\Facade', [
            'render',
            'renderFile',
            'renderDirectory',
            'compile',
            'compileFile',
            'compileDirectory' => 'textualCacheDirectory',
            'display'          => 'render',
            'displayFile'      => 'renderFile',
            'displayDirectory' => 'renderDirectory',
            'cacheDirectory'   => 'textualCacheDirectory',
        ]);

        $cliReturn = $cli->run([$command, 'compile-directory', 'views', 'cache', $json]);
        $cliOutput = ob_get_contents();

        ob_end_clean();

        // Optional step: the views directory is no longer needed as cached,
        // it can be emptied.
        unlink('views/index.pug');
        unlink('views/inc.pug');
        rmdir('views');

        // On runtime, use \Pug\Optimizer::call to render/display the pre-compiled views
        // with local variables
        $variables = [
            'foo' => 'FOO',
            'bar' => 'BAR',
        ];
        $content = Optimizer::call('renderFile', ['index', $variables], $options);

        // Unit test clean-up
        $this->removeDirectory($baseDir);

        $this->assertSame('<p>FOO</p><h1>BAR</h1>', $content);
        $this->assertTrue($cliReturn);
        $this->assertSame("2 templates cached.\n0 templates failed to be cached.\n", $cliOutput);
    }
}
