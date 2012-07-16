<?php

namespace Jade;

class Parser {

    protected $lexer;
    public $basepath; //current file basepath, used for include type
    public function __construct(Lexer $lexer) {
        $this->lexer = $lexer;
    }

    public function parse($input) {
        $source = (is_file($input)) ? file_get_contents($input) : (string)$input;
        $this->basepath = ( is_file($input) ) ? dirname($input) : false ;
        $this->lexer->setInput($source);

        $node = new Node('block');

        while ($this->lexer->lookahead()->type !== 'eos') {

            if ($this->lexer->lookahead()->type === 'newline') {
                $this->lexer->advanced();
			}
			else
			if ($this->lexer->lookahead()->type === 'include') {
                $node->addChildren($this->parseExpression());
            }
            else {
                $node->addChild($this->parseExpression());
            }
        }

        return $node;
    }

    protected function expectTokenType($type) {
        if ($this->lexer->lookahead()->type === $type) {
            return $this->lexer->advance();
        }

        throw new \Exception(sprintf('Expected %s, but got %s', $type, $this->lexer->lookahead()->type));
    }

    protected function acceptTokenType($type) {
        if ($this->lexer->lookahead()->type === $type) {
            return $this->lexer->advance();
        }
    }

    protected function parseExpression() {
        switch ( $this->lexer->lookahead()->type ) {
            case 'include':
                return $this->parseInclude();
            case 'tag':
                return $this->parseTag();
            case 'doctype':
                return $this->parseDoctype();
            case 'filter':
                return $this->parseFilter();
            case 'comment':
                return $this->parseComment();
            case 'text':
                return $this->parseText();
            case 'code':
                return $this->parseCode();
            case 'id':
            case 'class':
                $token = $this->lexer->advance();
                $this->lexer->defer($this->lexer->token('tag', 'div'));
                $this->lexer->defer($token);

                return $this->parseExpression();
        }
    }

    protected function parseText($trim = false) {
        $token = $this->expectTokenType('text');
        $value = $trim ? preg_replace('/^ +/', '', $token->value) : $token->value;

        return new Node('text', $value);
    }

    protected function parseCode() {
        $token  = $this->expectTokenType('code');
        $node   = new Node('code', $token->value, $token->buffer);
		$node->codeType = $token->code_type;

        // Skip newlines
        while ($this->lexer->lookahead()->type === 'newline') {
            $this->lexer->advance();
        }

        if ($this->lexer->lookahead()->type === 'indent') {
            $node->block = $this->parseBlock();
        }

        return $node;
    }

    protected function parseComment() {
        $token  = $this->expectTokenType('comment');
        $node   = new Node('comment', preg_replace('/^ +| +$/', '', $token->value), $token->buffer);

        // Skip newlines
        while ($this->lexer->lookahead()->type === 'newline') {
            $this->lexer->advance();
        }

        if ($this->lexer->lookahead()->type === 'indent') {
            $node->block = $this->parseBlock();
        }

        return $node;
    }

    protected function parseInclude() {
        $token = $this->expectTokenType('include');
        $filename = (strripos($token->value , ".jade", -5) !== False ) ? $token->value : $token->value.".jade";
        $source = realpath($this->basepath) . DIRECTORY_SEPARATOR . $filename;
        $l_parser = new Parser(new Lexer());
        return $l_parser->parse($source)->children;
    }

    protected function parseDoctype() {
        $token = $this->expectTokenType('doctype');

        return new Node('doctype', $token->value);
    }

    protected function parseFilter() {
        $block      = null;
        $token      = $this->expectTokenType('filter');
        $attributes = $this->acceptTokenType('attributes');

        if ( $this->lexer->lookahead(2)->type === 'text' ) {
            $block = $this->parseTextBlock();
        } else {
            $block = $this->parseBlock();
        }

        $node = new Node('filter', $token->value, null !== $attributes ? $attributes->attributes : array());
        $node->block = $block;

        return $node;
    }

    protected function parseTextBlock() {
        $node = new Node('text', null);

        $this->expectTokenType('indent');
        while ( $this->lexer->lookahead()->type === 'text' || $this->lexer->lookahead()->type === 'newline' ) {
            if ( $this->lexer->lookahead()->type === 'newline' ) {
                $this->lexer->advance();
            } else {
                $node->addLine($this->lexer->advance()->value);
            }
        }
        $this->expectTokenType('outdent');

        return $node;
    }

    protected function parseBlock() {
        $node = new Node('block');

        $this->expectTokenType('indent');
        while ( $this->lexer->lookahead()->type !== 'outdent' ) {
            if ( $this->lexer->lookahead()->type === 'newline' ) {
                $this->lexer->advance();
            }else if ( $this->lexer->lookahead()->type === 'include' ) {
                $node->addChildren($this->parseExpression());
            }else {
                $node->addChild($this->parseExpression());
            }
        }
        $this->expectTokenType('outdent');

        return $node;
    }

    protected function parseTag() {
        $name = $this->lexer->advance()->value;
        $node = new Node('tag', $name);

        // Parse id, class, attributes token
        while ( true ) {
            switch ( $this->lexer->lookahead()->type ) {
                case 'id':
                case 'class':
                    $token = $this->lexer->advance();
                    $node->setAttribute($token->type, $token->value);
                    continue;
                case 'attributes':
                    foreach ( $this->lexer->advance()->attributes as $name => $value ) {
                        $node->setAttribute($name, $value);
                    }
                    continue;
                default:
                    break(2);
            }
        }

        // Parse text/code token
        switch ( $this->lexer->lookahead()->type ) {
            case 'text':
                $node->text = $this->parseText(true);
                break;
            case 'code':
                $node->code = $this->parseCode();
                break;
        }

        // Skip newlines
        while ( $this->lexer->lookahead()->type === 'newline' ) {
            $this->lexer->advance();
        }

        // Tag text on newline
        if ( $this->lexer->lookahead()->type === 'text' ) {
            if ($text = $node->text) {
                $text->addLine('');
            } else {
                $node->text = new Node('text', '');
            }
        }

        // Parse block indentation
        if ( $this->lexer->lookahead()->type === 'indent' ) {
            $node->addChild($this->parseBlock());
        }

        return $node;
    }
}
