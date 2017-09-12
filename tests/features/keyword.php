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
        $pug = new Pug();
        $pug->addKeyword('foo', 'bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 31
     */
    public function testAddAlreadySetKeyword()
    {
        $pug = new Pug();
        $pug->addKeyword('foo', function () {
            return array();
        });
        $pug->addKeyword('foo', function () {
            return 'foo';
        });
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 32
     */
    public function testReplaceNonSetKeyword()
    {
        $pug = new Pug();
        $pug->replaceKeyword('foo', function () {
            return array();
        });
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 34
     */
    public function testBadReturn()
    {
        $pug = new Pug();
        $pug->addKeyword('foo', function () {
            return 32;
        });
        $pug->render('foo');
    }

    public function testBadReturnPreviousException()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not compatible with HHVM');
        }

        try {
            $pug = new Pug();
            $pug->addKeyword('foo', function () {
                return 32;
            });
            $pug->render('foo');
        } catch (\Exception $e) {
            $code = $e->getPrevious()->getCode();
        }

        $this->assertSame(33, $code, 'Expected previous exception code should be 8 for BadReturn.');
    }

    public function testBadCustomKeywordOptionType()
    {
        $pug = new Pug();
        $pug->setOption('customKeywords', new BadOptionType());
        $pug->addKeyword('foo', function () {
            return 'foo';
        });
        $this->assertSame('foo', $pug->render('foo'));
    }

    public function testPhpKeyWord()
    {
        $pug = new Pug([
            'debug' => true,
            'prettyprint' => false,
        ]);

        $actual = trim($pug->render('#{"for"};;'));
        $expected = '<for>;;</for>';
        $this->assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

        $pug->addKeyword('for', function ($args) {
            return array(
                'beginPhp' => 'for (' . $args . ') {',
                'endPhp' => '}',
            );
        });
        $actual = trim($pug->render(
            'for $i = 0; $i < 3; $i++' . "\n" .
            '  p= i'
        ));
        $expected = '<p>0</p><p>1</p><p>2</p>';
        $this->assertSame($expected, $actual, 'addKeyword should allow to customize available keywords.');
        $pug->replaceKeyword('for', new ForKeyword());
        $actual = trim($pug->render(
            'for $i = 0; $i < 3; $i++' . "\n" .
            '  p'
        ));
        $expected = '$i = 0; $i < 3; $i++<p></p>';
        $this->assertSame($expected, $actual, 'The keyword action can be an callable class.');

        $pug->removeKeyword('for');
        $actual = trim($pug->render('for ;;'));
        $expected = '<for>;;</for>';
        $this->assertSame($expected, $actual, 'After removing keyword, a word render as a tag.');
    }

    public function testHtmlKeyWord()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
        ]);

        $actual = trim($pug->render(
            "user Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<user>Bob<img src="bob.png"></user>';
        $this->assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

        $pug->addKeyword('user', function ($args) {
            return array(
                'begin' => '<div class="user" title="' . $args . '">',
                'end' => '</div>',
            );
        });
        $actual = trim($pug->render(
            "user Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<div class="user" title="Bob"><img src="bob.png"></div>';
        $this->assertSame($expected, $actual, 'addKeyword should allow to customize available tags.');
    }

    public function testKeyWordBeginAndEnd()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
        ]);

        $pug->setKeyword('foo', function ($args) {
            return 'bar';
        });
        $actual = trim($pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = 'bar<img src="bob.png">';
        $this->assertSame($expected, $actual, 'If addKeyword return a string, it\'s rendeder before the block.');

        $pug->setKeyword('foo', function ($args) {
            return array(
                'begin' => $args . '/',
            );
        });
        $actual = trim($pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = 'Bob/<img src="bob.png">';
        $this->assertSame($expected, $actual, 'If addKeyword return a begin entry, it\'s rendeder before the block.');

        $pug->setKeyword('foo', function ($args) {
            return array(
                'end' => 'bar',
            );
        });
        $actual = trim($pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<img src="bob.png">bar';
        $this->assertSame($expected, $actual, 'If addKeyword return an end entry, it\'s rendeder after the block.');
    }

    public function testKeyWordArguments()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
        ]);

        $foo = function ($args, $block, $keyWord) {
            return $keyWord;
        };
        $pug->setKeyword('foo', $foo);
        $actual = trim($pug->render("foo\n"));
        $expected = 'foo';
        $this->assertSame($expected, $actual);

        $pug->setKeyword('bar', $foo);
        $actual = trim($pug->render("bar\n"));
        $expected = 'bar';
        $this->assertSame($expected, $actual);

        $pug->setKeyword('minify', function ($args, $block) {
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
        $actual = trim($pug->render(
            "minify\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="foo-bar.min.css">';
        $this->assertSame($expected, $actual);

        $pug->setKeyword('concat-to', function ($args, $block) {
            $names = array();
            foreach ($block->nodes as $index => $tag) {
                if ($tag->name === 'link') {
                    unset($block->nodes[$index]);
                }
            }

            return '<link href="' . $args . '">';
        });
        $actual = trim($pug->render(
            "concat-to app.css\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="app.css">';
        $this->assertSame($expected, $actual);
    }
}
