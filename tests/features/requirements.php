<?php

use Pug\Pug;
use Pug\Test\AbstractTestCase;

class PugRequirementsTest extends AbstractTestCase
{
    public function testCacheFolderExists()
    {
        $pug = new Pug([
            'cache' => '/path/that/does/not/exists',
        ]);
        $requirements = $pug->requirements();
        self::assertFalse($requirements['cacheFolderExists'], 'cacheFolderExists requirement should be false with /path/that/does/not/exists');

        $pug = new Pug([
            'cache' => sys_get_temp_dir(),
        ]);
        $requirements = $pug->requirements();
        self::assertTrue($pug->requirements('cacheFolderExists'), 'cacheFolderExists requirement should be true with sys_get_temp_dir()');
    }

    public function testRequirementThatDoesNotExist()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(19);

        $pug = new Pug();
        $pug->requirements('requirementThatDoesNotExist');
    }
}
