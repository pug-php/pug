<?php

use Jade\Jade;

class JadeExpressionLanguageTest extends PHPUnit_Framework_TestCase
{
    public function testJsExpression()
    {
        $jade = new Jade(array(
            'singleQuote' => false,
            'expressionLanguage' => 'js',
        ));

        $actual = trim($jade->render("- a = 2\n- b = 4\n- c = b * a\np=a * b\np=c"));
        $this->assertSame('<p>8</p><p>8</p>', $actual);

        $actual = trim($jade->render("- a = 2\n- b = 4\np=a + b"));
        $this->assertSame('<p>6</p>', $actual);

        $actual = trim($jade->render("- a = '2'\n- b = 4\np=a + b"));
        $this->assertSame('<p>24</p>', $actual);
    }
}
