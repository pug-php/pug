<?php

use PHPUnit\Framework\TestCase;
use Pug\Pug;

class PugCachePerformanceTest extends TestCase
{
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
}
