<?php

use Jade\Jade;

class JadeFilterTest extends PHPUnit_Framework_TestCase {

    /**
     * custom filter test
     */
    public function testFilter() {

        $jade = new Jade();
        $this->assertFalse($jade->hasFilter('text'));
        $jade->filter('text', function($node, $compiler){
            foreach ($node->block->nodes as $line) {
                $output[] = $compiler->interpolate($line->value);
            }
            return strip_tags(implode(' ', $output));
        });
        $this->assertTrue($jade->hasFilter('text'));
        $actual = $jade->render('
div
    p
        :text
            article <span>foo</span> bar <img title="foo" />
            <div>section</div>
    :text
        <input /> form
        em strong quote code
');
        $expected = '<div><p>article foo bar section</p>form em strong quote code</div>';

        $this->assertSame(str_replace(' ', '', $actual), str_replace(' ', '', $expected), 'Custom filter');
    }
}
