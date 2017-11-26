<?php

use PHPUnit\Framework\TestCase;
use Pug\Pug;

class PugRequirementsTest extends TestCase
{
    public function testCacheFolderExists()
    {
        $pug = new Pug(array(
            'cache' => '/path/that/does/not/exists',
        ));
        $requirements = $pug->requirements();
        self::assertFalse($requirements['cacheFolderExists'], 'cacheFolderExists requirement should be false with /path/that/does/not/exists');

        $pug = new Pug(array(
            'cache' => sys_get_temp_dir(),
        ));
        $requirements = $pug->requirements();
        self::assertTrue($pug->requirements('cacheFolderExists'), 'cacheFolderExists requirement should be true with sys_get_temp_dir()');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 19
     */
    public function testRequirementThatDoesNotExist()
    {
        $pug = new Pug();
        $pug->requirements('requirementThatDoesNotExist');
    }
}
