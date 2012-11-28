<?php

namespace Jade;

use Jade\Nodes as Nodes;

require_once('Lexer.php');

class Parser {

    public $basepath;
    public static $extension = '.jade';
    public $textOnly = array('script','style');

    protected $input;
    protected $lexer;
    protected $filename;
    protected $extending;
    protected $blocks = array();
    protected $mixins = array();
    protected $contexts = array();

    public function __construct($str,$filename=null) {

        if ($filename == null && file_exists($str)) {
            $this->input = file_get_contents($str);
            $this->filename = $str;
        }else{
            $this->input = $str;
            $this->filename = $filename;
        }

        if($this->input[0] == "\xef" && $this->input[1] == "\xbb" && $this->input[2] == "\xbf")
            $this->input = substr($this->input, 3);

        $this->lexer = new Lexer($this->input);
        array_push($this->contexts, $this);
    }

    public function context($parser=null) {
        if ($parser===null) {
            return array_pop($this->contexts);
        }
        array_push($this->contexts, $parser);
    }

    public function advance() {
        return $this->lexer->advance();
    }

    public function skip($n) {
        while($n--) $this->advance();
    }

    public function peek() {
        return $this->lookahead(1);
    }

    public function line() {
        return $this->lexer->lineno;
    }

    public function lookahead($n=1) {
        return $this->lexer->lookahead($n);
    }

    public function parse() {
        $block = new Nodes\Block();
        $block->line = $this->line();

        while ($this->peek()->type !== 'eos') {

            if ($this->peek()->type === 'newline') {
                $this->advance();
            }
            else
            {
                $block->push($this->parseExpression());
            }
        }

        if ($parser = $this->extending) {
            $this->context($parser);
            //$parser->blocks = $this->blocks;
            $ast = $parser->parse();
            $this->context();

            foreach ($this->mixins as $name => $v) {
                $ast->unshift($this->mixins[$name]);
            }
            return $ast;
        }

        return $block;
    }

    protected function expect($type) {
        if ($this->peek()->type === $type) {
            return $this->lexer->advance();
        }

        throw new \Exception(sprintf('Expected %s, but got %s', $type, $this->peek()->type));
    }

    protected function accept($type) {
        if ($this->peek()->type === $type) {
            return $this->advance();
        }
    }

    protected function parseExpression() {
        $_types = array('tag','mixin','block','case','when','default','extends','include','doctype','filter','comment','text','each','code','call','interpolation');

        if (in_array($this->peek()->type, $_types)) {
            $_method = 'parse' . ucfirst($this->peek()->type);
            return $this->$_method();
        }

        switch ( $this->peek()->type ) {
        case 'yield':
            $this->advance();
            $block = new Nodes\Block();
            $block->yield = true;
            return $block;

        case 'id':
        case 'class':
            $token = $this->advance();
            $this->lexer->defer($this->lexer->token('tag', 'div'));
            $this->lexer->defer($token);
            return $this->parseExpression();

        default:
            throw new \Exception('Unexcpected token "' . $this->peek()->type . '"');
        }
    }

    protected function parseText($trim = false) {
        $token = $this->expect('text');
        $node = new Nodes\Text($token->value);
        $node->line = $this->line();
        return $node;
    }

    protected function parseBlockExpansion() {
        if (':' == $this->peek()->type) {
            $this->advance();
            return new Nodes\Block($this->parseExpression());
        }

        return $this->block();
    }

    protected function parseCase() {
        $value = $this->expect('case')->value;
        $node = new Nodes\CaseNode($value);
        $node->line = $this->line();
        $node->block = $this->block();
        return $node;
    }

    protected function parseWhen() {
        $value = $this->expect('when')->value;
        return new Nodes\When($value, $this->parseBlockExpansion());
    }

    protected function parseDefault() {
        $this->expect('default');
        return new Nodes\When('default', $this->parseBlockExpansion());
    }

    protected function parseCode() {
        $token  = $this->expect('code');
        $buffer = isset($token->buffer) ? $token->buffer : false;
        $escape = isset($token->escape) ? $token->escape : true;
        $node   = new Nodes\Code($token->value, $buffer, $escape);
        $node->line = $this->line();

        $i = 1;
        while ($this->lookahead($i)->type === 'newline') {
            $i++;
        }

        if ($this->lookahead($i)->type === 'indent') {
            $this->skip($i-1);
            $node->block = $this->block();
        }

        return $node;
    }

    protected function parseComment() {
        $token  = $this->expect('comment');

        if ($this->peek()->type === 'indent') {
            $node = new Nodes\BlockComment($token->value, $this->block(), $token->buffer);
        }else{
            $node = new Nodes\Comment($token->value, $token->buffer);
        }
        $node->line = $this->line();

        return $node;
    }

    protected function parseDoctype() {
        $token = $this->expect('doctype');
        $node =  new Nodes\Doctype($token->value);
        $node->line = $this->line();
        return $node;
    }

    protected function parseFilter() {
        $token      = $this->expect('filter');
        $attributes = $this->accept('attributes');

        $this->lexer->pipeless = true;
        $block = $this->parseTextBlock();
        $this->lexer->pipeless = false;

        $node = new Nodes\Filter($token->value, $block, $attributes);
        $node->line = $this->line();
        return $node;
    }

    protected function parseASTFilter() {
        $token = $this->expect('tag');
        $attributes = $this->accept('attributes');
        $this->expect(':');
        $block = $this->block();

        $node = new Nodes\Filter($token->value, $block, $attributes);
        $node->line = $this->line();
        return $node;
    }

    protected function parseEach() {
        $token = $this->expect('each');
        $node = new Nodes\Each($token->code, $token->value, $token->key);
        $node->line = $this->line();
        $node->block = $this->block();
        if ($this->peek()->type === 'code' && $this->peek()->value === 'else') {
                $this->advance();
                $node->alternative = $this->block();
        }
        return $node;
    }

    protected function parseExtends() {

        $file = $this->expect('extends')->value;
        $dir = realpath(dirname($this->filename));
        $path = $dir . DIRECTORY_SEPARATOR . $file . self::$extension;

        $string = file_get_contents($path);
        $parser = new Parser($string, $path);
        // need to be a reference, or be seted after the parse loop
        $parser->blocks = &$this->blocks;
        $parser->contexts = $this->contexts;
        $this->extending = $parser;

        return new Nodes\Literal('');
    }

    protected function parseBlock() {
        $block = $this->expect('block');
        $mode = $block->mode;
        $name = trim($block->value);

        $block = 'indent' == $this->peek()->type ? $this->block() : new Nodes\Block(new Nodes\Literal(''));

        if (isset($this->blocks[$name])) {
            $prev = $this->blocks[$name];

            switch ($prev->mode) {
            case 'append':
                $block->nodes = array_merge($block->nodes, $prev->nodes);
                $prev = $block;
                break;

            case 'prepend':
                $block->nodes = array_merge($prev->nodes, $block->nodes);
                $prev = $block;
                break;

            case 'replace':
            default:
                break;
            }

            $this->blocks[$name] = $prev;
        }else{
            $block->mode = $mode;
            $this->blocks[$name] = $block;
        }

        return $this->blocks[$name];
    }

    protected function parseInclude() {
        $token = $this->expect('include');
        $file = trim($token->value);
        $dir = realpath(dirname($this->filename));

        if( strpos(basename($file), '.') === false ){
            $file = $file . '.jade';
        }

        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $str = file_get_contents($path);

        if ('.jade' != substr($file,-5)) {
            return new Nodes\Literal($str);
        }

        $parser = new Parser($str, $path);
        $parser->blocks = $this->blocks;
        $parser->mixins = $this->mixins;

        $this->context($parser);
        $ast = $parser->parse();
        $this->context();
        $ast->filename = $path;

        if ('indent' == $this->peek()->type) {
            // includeBlock might not be set
            $block = $ast->includeBlock();
            if (is_object($block)) {
                $block->push($this->block());
            }
        }

        return $ast;
    }

    protected function parseCall() {
        $token = $this->expect('call');
        $name = $token->value;

        if (isset($token->arguments)) {
            $arguments = $token->arguments;
        }else{
            $arguments = null;
        }

        $mixin = new Nodes\Mixin($name, $arguments, new Nodes\Block(), true);

        $this->tag($mixin);

        if ($mixin->block->isEmpty()) {
            $mixin->block = null;
        }

        return $mixin;
    }

    protected function parseMixin() {
        $token = $this->expect('mixin');
        $name = $token->value;
        $arguments = $token->arguments;

        // definition
        if ('indent' == $this->peek()->type) {
            $mixin = new Nodes\Mixin($name, $arguments, $this->block(), false);
            $this->mixins[$name] = $mixin;
            return $mixin;
            // call
        }else{
            return new Nodes\Mixin($name, $arguments, null, true);
        }
    }

    protected function parseTextBlock() {
        $block = new Nodes\Block();
        $block->line = $this->line();
        $spaces = $this->expect('indent')->value;

        if (!isset($this->_spaces)) {
            $this->_spaces = $spaces;
        }

        $indent = str_repeat(' ', $spaces - $this->_spaces+1);

        while ($this->peek()->type != 'outdent') {

            switch ($this->peek()->type) {
            case 'newline':
                $this->lexer->advance();
                break;

            case 'indent':
                foreach ($this->parseTextBlock()->nodes as $n) {
                    $block->push($n);
                }
                break;

            default:
                $text = new Nodes\Text($indent . $this->advance()->value);
                $text->line = $this->line();
                $block->push($text);
            }
        }

        if (isset($this->_spaces) && $spaces == $this->_spaces) {
            unset($this->_spaces);
        }

        $this->expect('outdent');
        return $block;
    }

    protected function block() {
        $block = new Nodes\Block();
        $block->line = $this->line();
        $this->expect('indent');

        while ($this->peek()->type !== 'outdent' ) {

            if ($this->peek()->type === 'newline') {
                $this->lexer->advance();
            }else{
                $block->push($this->parseExpression());
            }
        }

        $this->expect('outdent');
        return $block;
    }

    protected function parseInterpolation() {
        $token = $this->advance();
        $tag = new Nodes\Tag($token->value);
        $tag->buffer = true;
        return $this->tag($tag);
    }

    protected function parseTag() {
        $i=2;

        if ('attributes' == $this->lookahead($i)->type) {
            $i++;
        }

        if (':' == $this->lookahead($i)->type) {
            $i++;

            if ('indent' == $this->lookahead($i)->type) {
                return $this->parseASTFilter();
            }
        }

        $token = $this->advance();
        $tag = new Nodes\Tag($token->value);

        // tag/
        if (isset($token->selfClosing)){
            $tag->selfClosing = $token->selfClosing;
        }else{
            $tag->selfClosing = false;
        }

        return $this->tag($tag);
    }

    protected function tag($tag) {
        $tag->line = $this->line();

        while (true) {

            switch ($this->peek()->type) {
            case 'id':
            case 'class':
                $token = $this->advance();
                $tag->setAttribute($token->type, "'" . $token->value . "'");
                continue;

            case 'attributes':
                $token = $this->advance();
                $obj = $token->attributes;
                $escaped = $token->escaped;
                $name_list = array_keys($obj);

                if ($token->selfClosing) {
                    $tag->selfClosing = true;
                }

                foreach ($name_list as $name) {
                    $value = $obj[$name];
                    $tag->setAttribute($name, $value, $escaped[$name]);
                }
                continue;

            default:
                break 2;
            }
        }

        $dot = false;
        $tag->textOnly = false;
        if ('.' == $this->peek()->value) {
            $dot = $tag->textOnly = true;
            $this->advance();
        }

        switch ($this->peek()->type) {
        case 'text':
            $tag->block->push($this->parseText());
            break;

        case 'code':
            $tag->code = $this->parseCode();
            break;

        case ':':
            $this->advance();
            $tag->block = new Nodes\Block();
            $tag->block->push($this->parseExpression());
            break;
        }

        while ('newline' == $this->peek()->type) {
            $this->advance();
        }

        if (in_array($tag->name, $this->textOnly)) {
            $tag->textOnly = true;
        }

        if ('script' == $tag->name) {
            $type = $tag->getAttribute('type');

            if ($type !== null) {
                $type = preg_replace('/^[\'\"]|[\'\"]$/','',$type);

                if (!$dot && 'text/javascript' != $type['value']) {
                    $tag->textOnly = false;
                }
            }
        }

        if ('indent' == $this->peek()->type) {
            if ($tag->textOnly) {
                $this->lexer->pipeless = true;
                $tag->block = $this->parseTextBlock();
                $this->lexer->pipeless = false;
            }else{
                $block = $this->block();
                if ($tag->block) {
                    foreach ($block->nodes as $n) {
                        $tag->block->push($n);
                    }
                }else{
                    $tag->block = $block;
                }
            }
        }

        return $tag;
    }
}
