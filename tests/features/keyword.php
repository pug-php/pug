<?php

use PHPUnit\Framework\TestCase;
use Phug\AbstractExtension;
use Phug\Compiler\Event\NodeEvent;
use Phug\Parser\Node\ElementNode;
use Pug\ExtensionContainerInterface;
use Pug\Pug;

class ForKeyword
{
    public function __invoke($args)
    {
        return $args;
    }
}

class PhugTestExtension extends AbstractExtension
{
    public function getEvents()
    {
        return [
            'on_node' => function (NodeEvent $event) {
                $node = $event->getNode();
                if ($node instanceof ElementNode) {
                    $node->setName('div');
                }
            },
        ];
    }
}

class KeywordWithExtension implements ExtensionContainerInterface
{
    public function getExtension()
    {
        return new PhugTestExtension();
    }

    public function __invoke($args)
    {
        return ['begin' => $args];
    }
}

class BadOptionType
{
}

class PugKeywordTest extends TestCase
{
    /**
     * @group keywords
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 30
     */
    public function testInvalidAction()
    {
        $pug = new Pug();
        $pug->addKeyword('foo', 'bar');
    }

    /**
     * @group keywords
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
     * @group keywords
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
     * @group keywords
     * @expectedException \Phug\FormatterException
     * @expectedExceptionMessage The keyword foo returned an invalid value type
     */
    public function testBadReturn()
    {
        $pug = new Pug();
        $pug->addKeyword('foo', function () {
            return 32;
        });
        $pug->render('foo');
    }

    /**
     * @group keywords
     */
    public function testBadCustomKeywordOptionType()
    {
        $pug = new Pug();
        $pug->setOption('customKeywords', new BadOptionType());
        $pug->addKeyword('foo', function () {
            return 'foo';
        });
        self::assertSame('foo', $pug->render('foo'));
    }

    /**
     * @group keywords
     */
    public function testPhpKeyWord()
    {
        $pug = new Pug([
            'debug' => true,
            'prettyprint' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $actual = trim($pug->render('#{"xfor"};;'));
        $expected = '<xfor>;;</xfor>';
        self::assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

        $pug->addKeyword('xfor', function ($args) {
            return array(
                'beginPhp' => 'for (' . $args . ') {',
                'endPhp' => '}',
            );
        });
        $actual = trim($pug->render(
            'xfor $i = 0; $i < 3; $i++' . "\n" .
            '  p= i'
        ));
        $expected = '<p>0</p><p>1</p><p>2</p>';
        self::assertSame($expected, $actual, 'addKeyword should allow to customize available keywords.');
        $pug->replaceKeyword('xfor', new ForKeyword());
        $actual = trim($pug->render(
            'xfor $i = 0; $i < 3; $i++' . "\n" .
            '  p'
        ));
        $expected = '$i = 0; $i < 3; $i++<p></p>';
        self::assertSame($expected, $actual, 'The keyword action can be an callable class.');

        $pug->removeKeyword('xfor');
        $actual = trim($pug->render('xfor ;;'));
        $expected = '<xfor>;;</xfor>';
        self::assertSame($expected, $actual, 'After removing keyword, a word render as a tag.');
    }

    /**
     * @group keywords
     */
    public function testHtmlKeyWord()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $actual = trim($pug->render(
            "user Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = '<user>Bob<img src="bob.png"></user>';
        self::assertSame($expected, $actual, 'Before adding keyword, a word render as a tag.');

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
        self::assertSame($expected, $actual, 'addKeyword should allow to customize available tags.');
    }

    /**
     * @group keywords
     */
    public function testKeyWordBeginAndEnd()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $pug->setKeyword('foo', function ($args) {
            return 'bar';
        });
        $actual = trim($pug->render(
            "foo Bob\n" .
            '  img(src="bob.png")'
        ));
        $expected = 'bar<img src="bob.png">';
        self::assertSame($expected, $actual, 'If addKeyword return a string, it\'s rendeder before the block.');

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
        self::assertSame($expected, $actual, 'If addKeyword return a begin entry, it\'s rendeder before the block.');

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
        self::assertSame($expected, $actual, 'If addKeyword return an end entry, it\'s rendeder after the block.');
    }

    /**
     * @group keywords
     */
    public function testKeyWordArguments()
    {
        $pug = new Pug([
            'debug' => true,
            'singleQuote' => false,
            'prettyprint' => false,
            'default_format' => \Phug\Formatter\Format\HtmlFormat::class,
        ]);

        $foo = function ($args, $block, $keyWord) {
            return $keyWord;
        };
        $pug->setKeyword('foo', $foo);
        $actual = trim($pug->render("foo\n"));
        $expected = 'foo';
        self::assertSame($expected, $actual);

        $pug->setKeyword('bar', $foo);
        $actual = trim($pug->render("bar\n"));
        $expected = 'bar';
        self::assertSame($expected, $actual);

        $pug->setKeyword('minify', function ($args, $block) {
            $names = array();
            $nodes = array();
            foreach ($block->nodes as $index => $tag) {
                if ($tag->name === 'link') {
                    $href = $tag->getAttribute('href');
                    $names[] = substr($href->getValue(), 0, -4);
                    continue;
                }
                $nodes[] = $tag;
            }
            $block->nodes = $nodes;

            return '<link href="' . implode('-', $names) . '.min.css">';
        });
        $actual = trim($pug->render(
            "minify\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="foo-bar.min.css">';
        self::assertSame($expected, $actual);

        $pug->setKeyword('concat-to', function ($args, $block) {
            $names = array();
            $nodes = array();
            foreach ($block->nodes as $index => $tag) {
                if ($tag->name === 'link') {
                    continue;
                }
                $nodes[] = $tag;
            }
            $block->nodes = $nodes;

            return '<link href="' . $args . '">';
        });
        $actual = trim($pug->render(
            "concat-to app.css\n" .
            "  link(href='foo.css')\n" .
            "  link(href='bar.css')\n"
        ));
        $expected = '<link href="app.css">';
        self::assertSame($expected, $actual);
    }

    public function testKeywordWithExtension()
    {
        $pug = new Pug([
            'keywords' => [
                'foo' => new KeywordWithExtension(),
            ],
        ]);

        $html = trim($pug->render("foo bar\n  bar"));

        self::assertSame('bar<div></div>', $html);
        $pug = new Pug();
        $pug->setKeyword('foo', new KeywordWithExtension());

        $html = trim($pug->render("foo bar\n  bar"));

        self::assertSame('bar<div></div>', $html);
    }
}
