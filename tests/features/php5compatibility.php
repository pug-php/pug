<?php

use Pug\Pug;

class Php5CompatibilityTest extends PHPUnit_Framework_TestCase
{
    public function testPhp5CompatibilityOption()
    {
        $pug = new Pug(array(
            'php5compatibility' => true,
        ));

        $php = $pug->compile('h1=user.getName()');

        $this->assertSame(
            '<h1><?php echo \Pug\Compiler::getEscapedValue(' .
                'call_user_func(\Pug\Compiler::getPropertyFromAnything($user, \'getName\')), '.
                '\'"\'' .
            ') ?></h1>',
            $php
        );
    }
}
