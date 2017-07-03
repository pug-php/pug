<?php

use Pug\Pug;

class ForKeyword
{
    public function __invoke($args)
    {
        return $args;
    }
}

class BadOptionType
{
}

class PugKeywordTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 30
     */
    public function testInvalidAction()
    {
        $Pug = new Pug();
        $Pug->addKeyword('foo', 'bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 31
     */
    public function testAddAlreadySetKeyword()
    {
        $Pug = new Pug();
        $Pug->addKeyword('foo', function () {
            return array();
        });
        $Pug->addKeyword('foo', function () {
            return 'foo';
        });
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 32
     */
    public function testReplaceNonSetKeyword()
    {
        $Pug = new Pug();
        $Pug->replaceKeyword('foo', function () {
            return array();
        });
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 34
     */
    public function testBadReturn()
    {
        $Pug = new Pug();
        $Pug->addKeyword('foo', function () {
            return 32;
        });
        $Pug->render('foo');
    }

    public function testBadReturnPreviousException()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not compatible with HHVM');
        }

        try {
            $Pug = new Pug();
            $Pug->addKeyword('foo', function () {
                return 32;
            });
            $Pug->render('foo');
        } catch (\Exception $e) {
            $code = $e->getPrevious()->getCode();
        }

        $this->assertSame(33, $code, 'Expected previous exception code should be 8 for BadReturn.');
    }

    public function testBadCustomKeywordOptionType()
    {
        $Pug = new Pug();
        $Pug->setOption('customKeywords', new BadOptionType());
        $Pug->addKeyword('foo', function () {
            return 'foo';
        });
        $this->assertSame('foo', $Pug->render('foo'));
    }

    public function testPhpKeyWord()
    {
        $Pug = new Pug(array(
            'prettyprint' => false,
        ));

        $actual = trim($Pug->render('for ;;'));
        $expected = '<for>;;</for>';
        $this->assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

        $Pug->addKeyword('for', function ($args) {
            return array(
                'beginPhp' => 'for (' . $args . ') {',
                'endPhp' => '}',
            );
        });
        $actual = trim($Pug->render(
            'for $i = 0; $i < 3; $i++' . "\n" .
            '  p= i'
        ));
        $expected = '<p>0</p><p>1</p><p>2</p>';
        $this->assertSame($expected, $actual, 'addKeyword should allow to customize available keywords.');
        $Pug->replaceKeyword('for', new ForKeyword());
        $actual = trim($Pug->render(
            'for $i = 0; $i < 3; $i++' . "\n" .
            '  p'
        ));
        $expected = '$i = 0; $i < 3; $i++<p></p>';
        $this->assertSame($expected, $actual, 'The keyword action can be an callable class.');

        $Pug->removeKeyword('for');
        $actual = trim($Pug->render('for ;;'));
        $expected = '<for>;;</for>';
        $this->assertSame($expected, $actual, 'After removing keyword, a word render as a tag.');
    }

    public function testHtmlKeyWord()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => false,
        ));

        $actual = trim($Pug->render(
            "user Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<user>Bob<img src="bob.png"></user>';
        $this->assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

        $Pug->addKeyword('user', function ($args) {
            return array(
                'begin' => '<div class="user" title="' . $args . '">',
                'end' => '</div>',
            );
        });
        $actual = trim($Pug->render(
            "user Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<div class="user" title="Bob"><img src="bob.png"></div>';
        $this->assertSame($expected, $actual, 'addKeyword should allow to customize available tags.');
    }

    public function testKeyWordBeginAndEnd()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => false,
        ));

        $Pug->setKeyword('foo', function ($args) {
            return 'bar';
        });
        $actual = trim($Pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = 'bar<img src="bob.png">';
        $this->assertSame($expected, $actual, 'If addKeyword return a string, it\'s rendeder before the block.');

        $Pug->setKeyword('foo', function ($args) {
            return array(
                'begin' => $args . '/',
            );
        });
        $actual = trim($Pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = 'Bob/<img src="bob.png">';
        $this->assertSame($expected, $actual, 'If addKeyword return a begin entry, it\'s rendeder before the block.');

        $Pug->setKeyword('foo', function ($args) {
            return array(
                'end' => 'bar',
            );
        });
        $actual = trim($Pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<img src="bob.png">bar';
        $this->assertSame($expected, $actual, 'If addKeyword return an end entry, it\'s rendeder after the block.');
    }

    public function testKeyWordArguments()
    {
        $Pug = new Pug(array(
            'singleQuote' => false,
            'prettyprint' => false,
        ));

        $foo = function ($args, $block, $keyWord) {
            return $keyWord;
        };
        $Pug->setKeyword('foo', $foo);
        $actual = trim($Pug->render("foo\n"));
        $expected = 'foo';
        $this->assertSame($expected, $actual);

        $Pug->setKeyword('bar', $foo);
        $actual = trim($Pug->render("bar\n"));
        $expected = 'bar';
        $this->assertSame($expected, $actual);

        $Pug->setKeyword('minify', function ($args, $block) {
            $names = array();
            foreach ($block->nodes as $index => $tag) {
                if ($tag->name === 'link') {
                    $href = $tag->getAttribute('href');
                    $names[] = substr($href['value'], 1, -5);
                    unset($block->nodes[$index]);
                }
            }

            return '<link href="' . implode('-', $names) . '.min.css">';
        });
        $actual = trim($Pug->render(
            "minify\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="foo-bar.min.css">';
        $this->assertSame($expected, $actual);

        $Pug->setKeyword('concat-to', function ($args, $block) {
            $names = array();
            foreach ($block->nodes as $index => $tag) {
                if ($tag->name === 'link') {
                    unset($block->nodes[$index]);
                }
            }

            return '<link href="' . $args . '">';
        });
        $actual = trim($Pug->render(
            "concat-to app.css\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="app.css">';
        $this->assertSame($expected, $actual);
    }
}
