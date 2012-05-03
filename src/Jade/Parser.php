<?php

namespace Jade;

class Parser {

    protected $lexer;
    public $basepath; //current file basepath, used for include type
    public function __construct(Lexer $lexer) {
        $this->lexer = $lexer;
    }

    public function parse($input) {
        $source = ( is_file($input) ) ? file_get_contents($input) : (string) $input;
        $this->basepath = ( is_file($input) ) ? dirname($input) : False ;
        $this->lexer->setInput($source);

        $node = new Node('block');

        while ( $this->lexer->predictToken()->type !== 'eos' ) {
            //echo $this->lexer->predictToken()->type.PHP_EOL;
            if ( $this->lexer->predictToken()->type === 'newline' ) {
                $this->lexer->getAdvancedToken();
            }else if ( $this->lexer->predictToken()->type === 'include' ) {
                $node->addChildren($this->parseExpression());
            }
            else {
                $node->addChild($this->parseExpression());
            }
        }

        return $node;
    }

    protected function expectTokenType($type) {
        if ( $this->lexer->predictToken()->type === $type ) {
            return $this->lexer->getAdvancedToken();
        } else {
            throw new \Exception(sprintf('Expected %s, but got %s', $type, $this->lexer->predictToken()->type));
        }
    }

    protected function acceptTokenType($type) {
        if ( $this->lexer->predictToken()->type === $type ) {
            return $this->lexer->getAdvancedToken();
        }
    }

    protected function parseExpression() {
        switch ( $this->lexer->predictToken()->type ) {
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
                $token = $this->lexer->getAdvancedToken();
                $this->lexer->deferToken($this->lexer->takeToken('tag', 'div'));
                $this->lexer->deferToken($token);

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
        while ( $this->lexer->predictToken()->type === 'newline' ) {
            $this->lexer->getAdvancedToken();
        }

        if ( $this->lexer->predictToken()->type === 'indent' ) {
            $node->block = $this->parseBlock();
        }

        return $node;
    }

    protected function parseComment() {
        $token  = $this->expectTokenType('comment');
        $node   = new Node('comment', preg_replace('/^ +| +$/', '', $token->value), $token->buffer);

        // Skip newlines
        while ( $this->lexer->predictToken()->type === 'newline' ) {
            $this->lexer->getAdvancedToken();
        }

        if ( $this->lexer->predictToken()->type === 'indent' ) {
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

        if ( $this->lexer->predictToken(2)->type === 'text' ) {
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
        while ( $this->lexer->predictToken()->type === 'text' || $this->lexer->predictToken()->type === 'newline' ) {
            if ( $this->lexer->predictToken()->type === 'newline' ) {
                $this->lexer->getAdvancedToken();
            } else {
                $node->addLine($this->lexer->getAdvancedToken()->value);
            }
        }
        $this->expectTokenType('outdent');

        return $node;
    }

    protected function parseBlock() {
        $node = new Node('block');

        $this->expectTokenType('indent');
        while ( $this->lexer->predictToken()->type !== 'outdent' ) {
            if ( $this->lexer->predictToken()->type === 'newline' ) {
                $this->lexer->getAdvancedToken();
            }else if ( $this->lexer->predictToken()->type === 'include' ) {
                $node->addChildren($this->parseExpression());
            }else {
                $node->addChild($this->parseExpression());
            }
        }
        $this->expectTokenType('outdent');

        return $node;
    }

    protected function parseTag() {
        $name = $this->lexer->getAdvancedToken()->value;
        $node = new Node('tag', $name);

        // Parse id, class, attributes token
        while ( true ) {
            switch ( $this->lexer->predictToken()->type ) {
                case 'id':
                case 'class':
                    $token = $this->lexer->getAdvancedToken();
                    $node->setAttribute($token->type, $token->value);
                    continue;
                case 'attributes':
                    foreach ( $this->lexer->getAdvancedToken()->attributes as $name => $value ) {
                        $node->setAttribute($name, $value);
                    }
                    continue;
                default:
                    break(2);
            }
        }

        // Parse text/code token
        switch ( $this->lexer->predictToken()->type ) {
            case 'text':
                $node->text = $this->parseText(true);
                break;
            case 'code':
                $node->code = $this->parseCode();
                break;
        }

        // Skip newlines
        while ( $this->lexer->predictToken()->type === 'newline' ) {
            $this->lexer->getAdvancedToken();
        }

        // Tag text on newline
        if ( $this->lexer->predictToken()->type === 'text' ) {
            if ($text = $node->text) {
                $text->addLine('');
            } else {
                $node->text = new Node('text', '');
            }
        }

        // Parse block indentation
        if ( $this->lexer->predictToken()->type === 'indent' ) {
            $node->addChild($this->parseBlock());
        }

        return $node;
    }
}
?>
