<?php

use Jade\Jade;

class JadeCachePerformanceTest extends PHPUnit_Framework_TestCase
{
    protected function getPerformanceTemplate($template)
    {
        return TEMPLATES_DIRECTORY . DIRECTORY_SEPARATOR . 'performance' . DIRECTORY_SEPARATOR . $template . '.jade';
    }

    protected function getPhpFromTemplate($template)
    {
        return $this->getPhp(file_get_contents($this->getPerformanceTemplate($template)));
    }

    protected function getPhp($template)
    {
        $jade = new Jade(array(
            'singleQuote' => false,
            'prettyprint' => false,
            'restrictedScope' => true,
        ));

        return trim($jade->compile($template));
    }

    protected function getHtmlFromTemplate($template, array $vars = array())
    {
        $jade = new Jade(array(
            'singleQuote' => false,
            'prettyprint' => false,
            'restrictedScope' => true,
        ));

        return trim($jade->render($this->getPerformanceTemplate($template), $vars));
    }

    /**
     * Cache weight.
     */
    public function testCacheWeihgt()
    {
        $this->assertSame('<p>Hello world!</p>', $this->getPhp('p Hello world!'), 'Simple template should output simple code.');

        return;
        $phpSize = strlen($this->getPhpFromTemplate('mixin'));
        $htmlSize = strlen($this->getHtmlFromTemplate('mixin'));
        echo "\n\n\n\n\n" . $this->getPhpFromTemplate('mixin') . "\n   => $phpSize\n\n" . $this->getHtmlFromTemplate('mixin') . "\n   => $htmlSize\n";
        $this->assertLessThan($htmlSize, $phpSize, 'Mixins used twice should remains shorter than the flat HTML.');

        $phpSize = strlen($this->getPhpFromTemplate('each'));
        $htmlSize = strlen($this->getHtmlFromTemplate('each'));
        echo "\n\n\n\n\n" . $this->getPhpFromTemplate('each') . "\n   => $phpSize\n\n" . $this->getHtmlFromTemplate('each') . "\n   => $htmlSize\n";
        $this->assertLessThan($htmlSize, $phpSize, 'Each used twice should remains shorter than the flat HTML.');
    }
}
