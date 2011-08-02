<?php

require '../work.php';
require '../lib/node/Node.php';
require '../lib/node/BlockNode.php';
require '../lib/node/CodeNode.php';
require '../lib/node/CommentNode.php';
require '../lib/node/DoctypeNode.php';
require '../lib/node/FilterNode.php';
require '../lib/node/TagNode.php';
require '../lib/node/TextNode.php';
require '../lib/Dumper.php';
require '../lib/Lexer.php';
require '../lib/Parser.php';
require '../Jade.php';



class JadeTest extends \PHPUnit_Framework_TestCase {

    protected $jade;

    public function __construct() {
        $parser = new Parser(new Lexer());
        $dumper = new Dumper();

        $this->jade = new Jade($parser, $dumper);
    }

    protected function parse($value) {
        return $this->jade->render($value);
    }

    public function testDoctypes() {
        $this->assertEquals('<?xml version="1.0" encoding="utf-8" ?>' , $this->parse('!!! xml'));
        $this->assertEquals('<!DOCTYPE html>' , $this->parse('!!! 5'));
    }

    public function testLineEndings() {
        $tags = array('p', 'div', 'img');
        $html = implode("\n", array('<p></p>', '<div></div>', '<img />'));

        $this->assertEquals($html, $this->parse(implode("\r\n", $tags)));
        $this->assertEquals($html, $this->parse(implode("\r", $tags)));
        $this->assertEquals($html, $this->parse(implode("\n", $tags)));
    }

    public function testSingleQuotes() {
        $this->assertEquals("<p>'foo'</p>", $this->parse("p 'foo'"));
        $this->assertEquals("<p>\n  'foo'\n</p>", $this->parse("p\n  | 'foo'"));
        $this->assertEquals(<<<HTML
<?php \$path = 'foo' ?>
<a href="/<?php echo jade\jade_text(\$path) ?>"></a>
HTML
, $this->parse(<<<Jade
- \$path = 'foo'
a(href='/#{path}')
Jade
));
    }

    public function testTags() {
        $str = implode("\n", array('p', 'div', 'img'));
        $html = implode("\n", array('<p></p>', '<div></div>', '<img />'));

        $this->assertEquals($html, $this->parse($str), 'Test basic tags');
        $this->assertEquals('<div id="item" class="something"></div>',
            $this->parse('#item.something'), 'Test classes');
        $this->assertEquals('<div class="something"></div>', $this->parse('div.something'),
            'Test classes');
        $this->assertEquals('<div id="something"></div>', $this->parse('div#something'),
            'Test ids');
        $this->assertEquals('<div class="something"></div>', $this->parse('.something'),
            'Test stand-alone classes');
        $this->assertEquals('<div id="something"></div>', $this->parse('#something'),
            'Test stand-alone ids');
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->parse('#foo.bar'));
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->parse('.bar#foo'));
        $this->assertEquals('<div id="foo" class="bar"></div>',
            $this->parse('div#foo(class="bar")'));
        $this->assertEquals('<div id="foo" class="bar"></div>',
            $this->parse('div(class="bar")#foo'));
        $this->assertEquals('<div id="bar" class="foo"></div>',
            $this->parse('div(id="bar").foo'));
        $this->assertEquals('<div class="foo bar baz"></div>', $this->parse('div.foo.bar.baz'));
        $this->assertEquals('<div class="foo bar baz"></div>',
            $this->parse('div(class="foo").bar.baz'));
        $this->assertEquals('<div class="foo bar baz"></div>',
            $this->parse('div.foo(class="bar").baz'));
        $this->assertEquals('<div class="foo bar baz"></div>',
            $this->parse('div.foo.bar(class="baz")'));
        $this->assertEquals('<div class="a-b2"></div>',
            $this->parse('div.a-b2'));
        $this->assertEquals('<div class="a_b2"></div>',
            $this->parse('div.a_b2'));
        $this->assertEquals('<fb:user></fb:user>',
            $this->parse('fb:user'));
    }


    public function testVariableLengthNewlines() {
        $jade = <<<Jade
ul
  li a

  li b


  li
    ul
      li c

      li d
  li e
Jade;
        $html = <<<HTML
<ul>
  <li>a</li>
  <li>b</li>
  <li>
    <ul>
      <li>c</li>
      <li>d</li>
    </ul>
  </li>
  <li>e</li>
</ul>
HTML;
        $this->assertEquals($html, $this->parse($jade));
    }

    public function testNewlines() {
        $jade = <<<Jade
ul
  li a




  li b
  li


    ul

      li c
      li d

  li e
Jade;
        $html = <<<HTML
<ul>
  <li>a</li>
  <li>b</li>
  <li>
    <ul>
      <li>c</li>
      <li>d</li>
    </ul>
  </li>
  <li>e</li>
</ul>
HTML;
        $this->assertEquals($html, $this->parse($jade));

        $jade = <<<Jade
ul
  li visit
    a(href="/foo") foo
Jade;
        $html = <<<HTML
<ul>
  <li>
    visit
    <a href="/foo">foo</a>
  </li>
</ul>
HTML;
        $this->assertEquals($html, $this->parse($jade));
    }

    public function testTagText() {
        $this->assertEquals('some random text', $this->parse('| some random text'));
        $this->assertEquals('<p>some random text</p>', $this->parse('p some random text'));
    }

    public function testTagTextBlock() {
        $this->assertEquals("<p>\n  foo\n     bar\n   baz\n</p>", $this->parse("p\n  | foo\n  |    bar\n  |  baz"));
        $this->assertEquals("<label>\n  Password:\n  <input />\n</label>", $this->parse("label\n  | Password:\n  input"));
    }

    public function testTagTextCodeInsertion() {
        $this->assertEquals('yo, <?php echo $jade ?> is cool', $this->parse('| yo, <?php echo $jade ?> is cool'));
        $this->assertEquals('<p>yo, <?php echo $jade ?> is cool</p>', $this->parse('p yo, <?php echo $jade ?> is cool'));
        $this->assertEquals('<p>yo, <?php echo $jade || $jade ?> is cool</p>', $this->parse('p yo, <?php echo $jade || $jade ?> is cool'));
        $this->assertEquals('yo, <?php echo $jade || $jade ?> is cool', $this->parse('| yo, <?php echo $jade || $jade ?> is cool'));
    }

    public function testHtml5Mode() {
        $this->assertEquals("<!DOCTYPE html>\n<input type=\"checkbox\" checked=\"checked\" />", $this->parse("!!! 5\ninput(type=\"checkbox\", checked)"));
//        $this->assertEquals("<!DOCTYPE html>\n<input type=\"checkbox\" checked=\"checked\" />", $this->parse("!!! 5\ninput(type=\"checkbox\", checked: true)"));
//        $this->assertEquals("<!DOCTYPE html>\n<input type=\"checkbox\" />", $this->parse("!!! 5\ninput(type=\"checkbox\", checked: false)"));
    }

    public function testAttrs() {
        $this->assertEquals('<img src="&lt;script&gt;" />', $this->parse('img(src="<script>")'), 'Test attr escaping');
        $this->assertEquals('<a data-attr="bar"></a>', $this->parse('a(data-attr:"bar")'));
        $this->assertEquals('<a data-attr="bar" data-attr-2="baz"></a>', $this->parse('a(data-attr:"bar", data-attr-2:"baz")'));
//        $this->assertEquals('<a title="foo,bar"></a>', $this->parse('a(title: "foo,bar")'));
//        $this->assertEquals('<a title="foo , bar"></a>', $this->parse('a(title: "foo , bar" )'));
//        $this->assertEquals('<a title="foo,bar" href="#"></a>', $this->parse('a(title: "foo,bar", href="#")'));

        $this->assertEquals('<p class="foo"></p>', $this->parse("p(class='foo')"), 'Test single quoted attrs');
        $this->assertEquals('<input type="checkbox" checked="checked" />', $this->parse('input(type="checkbox", checked)'));
//        $this->assertEquals('<input type="checkbox" checked="checked" />', $this->parse('input(type="checkbox", checked: true)'));
//        $this->assertEquals('<input type="checkbox" />', $this->parse('input(type="checkbox", checked: false)'));
//        $this->assertEquals('<input type="checkbox" />', $this->parse('input(type="checkbox", checked: null)'));
//        $this->assertEquals('<input type="checkbox" />', $this->parse('input(type="checkbox", checked: "")'));

        $this->assertEquals('<img src="/foo.png" />', $this->parse('img(src="/foo.png")'), 'Test attr =');
//        $this->assertEquals('<img src="/foo.png" />', $this->parse('img(src  =  "/foo.png")'), 'Test attr = whitespace');
//        $this->assertEquals('<img src="/foo.png" />', $this->parse('img(src:"/foo.png")'), 'Test attr :');
//        $this->assertEquals('<img src="/foo.png" />', $this->parse('img(src  :  "/foo.png")'), 'Test attr : whitespace');

//        $this->assertEquals('<img src="/foo.png" alt="just some foo" />',
//            $this->parse('img(src: "/foo.png", alt: "just some foo")'));
//        $this->assertEquals('<img src="/foo.png" alt="just some foo" />',
//            $this->parse('img(src   : "/foo.png", alt  :  "just some foo")'));
//        $this->assertEquals('<img src="/foo.png" alt="just some foo" />',
//            $this->parse('img(src="/foo.png", alt="just some foo")'));
//        $this->assertEquals('<img src="/foo.png" alt="just some foo" />',
//            $this->parse('img(src = "/foo.png", alt = "just some foo")'));

//        $this->assertEquals('<p class="foo,bar,baz"></p>', $this->parse('p(class="foo,bar,baz")'));
//        $this->assertEquals('<a href="http://google.com" title="Some : weird = title"></a>',
//            $this->parse('a(href: "http://google.com", title: "Some : weird = title")'));
//        $this->assertEquals('<label for="name"></label>',
//            $this->parse('label(for="name")'));
//        $this->assertEquals('<meta name="viewport" content="width=device-width" />',
//            $this->parse("meta(name: 'viewport', content: 'width=device-width')"), 'Attrs with separators');
//        $this->assertEquals('<meta name="viewport" content="width=device-width" />',
//            $this->parse("meta(name: 'viewport', content='width=device-width')"), 'Attrs with separators');
//        $this->assertEquals('<div style="color: white"></div>',
//            $this->parse("div(style='color: white')"));
//        $this->assertEquals('<p class="foo"></p>',
//            $this->parse("p('class'='foo')"), 'Keys with single quotes');
//        $this->assertEquals('<p class="foo"></p>',
//            $this->parse("p(\"class\": 'foo')"), 'Keys with double quotes');
//
//        $this->assertEquals(
//          '<meta name="viewport" content="width=device-width, user-scalable=no" />',
//          $this->parse(
//            "meta(name: 'viewport', content:\"width=device-width, user-scalable=no\")"
//          ), 'Commas in attrs'
//        );
//        $this->assertEquals(
//          '<meta name="viewport" content="width=device-width, user-scalable=no" />',
//          $this->parse(
//            "meta(name: 'viewport', content:'width=device-width, user-scalable=no')"
//          ), 'Commas in attrs'
//        );
    }

    public function testCodeAttrs() {
		/*
        $this->assertEquals('<p id="<?php echo $name ?>"></p>', $this->parse('p(id: {{$name}})'));
        $this->assertEquals('<p id="<?php echo \'name \' . $name . " =)" ?>"></p>', $this->parse('p(id: {{\'name \' . $name . " =)"}})'));
        $this->assertEquals('<p foo="<?php echo $name || "<default />" ?>"></p>', $this->parse('p(foo: {{$name || "<default />"}})'));
        $this->assertEquals('<p id="<?php echo \'name \' . $name . " =)" ?>">Hello, (bracket =) )</p>', $this->parse('p(id: {{\'name \' . $name . " =)"}}) Hello, (bracket =) )'));
		*/
    }

    public function testCode() {
    }

    public function test18NStringInAttrs() {
        $this->assertEquals('<input type="text" value="Search" />', $this->parse('input( type="text", value="Search" )'));
        $this->assertEquals('<input type="текст" value="Поиск" />', $this->parse('input( type="текст", value="Поиск" )'));
    }

    public function testHTMLComments() {
        $jade = <<<Jade
// just some paragraphs
p foo
p bar
Jade;
        $html = <<<HTML
<!-- just some paragraphs -->
<p>foo</p>
<p>bar</p>
HTML;
        $this->assertEquals($html, $this->parse($jade));

        $jade = <<<Jade
body
  //
    #content
      h1 Example
Jade;
        $html = <<<HTML
<body>
  <!--
    <div id="content">
      <h1>Example</h1>
    </div>
  -->
</body>
HTML;

        $this->assertEquals($html, $this->parse($jade));
    }

    public function testHTMLConditionalComments() {
        $jade = <<<Jade
body
  //if IE
    a(href='http://www.mozilla.com/en-US/firefox/') Get Firefox
Jade;
        $html = <<<HTML
<body>
  <!--[if IE]>
    <a href="http://www.mozilla.com/en-US/firefox/">Get Firefox</a>
  <![endif]-->
</body>
HTML;
        $this->assertEquals($html, $this->parse($jade), 'Conditional comments should be supported.');

        $jade = <<<Jade
!!! 5
html
  - use_helper('LESS')

  head
    - include_http_metas()
    - include_metas()
    - include_title()
    - include_less_stylesheets()
    - include_javascripts()

    //-
      p
        | Not printed

    // if lt IE 9
      script( src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js" )
    // if IE
      script( src="http://html5shiv.googlecode.com/svn/trunk/html5.js" )
      script( src="http://html5shiv.googlecode.com/svn/trunk/html6.js" )
      script( src="http://html5shiv.googlecode.com/svn/trunk/html7.js" )
Jade;
        $html = <<<HTML
<!DOCTYPE html>
<html>
  <?php use_helper('LESS') ?>
  <head>
    <?php include_http_metas() ?>
    <?php include_metas() ?>
    <?php include_title() ?>
    <?php include_less_stylesheets() ?>
    <?php include_javascripts() ?>
    <!--[if lt IE 9]>
      <script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>
    <![endif]-->
    <!--[if IE]>
      <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
      <script src="http://html5shiv.googlecode.com/svn/trunk/html6.js"></script>
      <script src="http://html5shiv.googlecode.com/svn/trunk/html7.js"></script>
    <![endif]-->
  </head>
</html>
HTML;
        $this->assertEquals($html, $this->parse($jade));
    }
}
