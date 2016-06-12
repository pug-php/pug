<?php

namespace Jade\Compiler;

use Jade\Nodes\Block;
use Jade\Nodes\BlockComment;
use Jade\Nodes\CaseNode;
use Jade\Nodes\Comment;
use Jade\Nodes\Doctype;
use Jade\Nodes\Each;
use Jade\Nodes\Filter;
use Jade\Nodes\Literal;
use Jade\Nodes\Node;
use Jade\Nodes\When;

abstract class Visitor extends AttributesCompiler
{
    /**
     * @param Nodes\Node $node
     *
     * @return array
     */
    public function visit(Node $node)
    {
        $this->visitNode($node);

        return $this->buffer;
    }

    /**
     * @param Nodes\Node $node
     *
     * @return mixed
     */
    protected function visitNode(Node $node)
    {
        $fqn = get_class($node);
        $parts = explode('\\', $fqn);
        $name = end($parts);
        $method = 'visit' . ucfirst(strtolower($name));

        return $this->$method($node);
    }

    /**
     * @param Nodes\CaseNode $node
     */
    protected function visitCasenode(CaseNode $node)
    {
        $this->switchNode = $node;
        $this->visit($node->block);

        if (!isset($this->switchNode)) {
            unset($this->switchNode);
            $this->indents--;

            $code = $this->createCode('}');
            $this->buffer($code);
        }
    }

    /**
     * @param Nodes\When $node
     */
    protected function visitWhen(When $node)
    {
        $code = '';
        $arguments = array();

        if (isset($this->switchNode)) {
            $code .= 'switch (%s) {';
            $arguments[] = $this->switchNode->expr;
            unset($this->switchNode);

            $this->indents++;
        }
        if ('default' == $node->expr) {
            $code .= 'default:';
        } else {
            $code .= 'case %s:';
            $arguments[] = $node->expr;
        }

        array_unshift($arguments, $code);

        $code = call_user_func_array(array($this, 'createCode'), $arguments);

        $this->buffer($code);

        $this->visit($node->block);

        $code = $this->createCode('break;');
        $this->buffer($code . $this->newline());
    }

    /**
     * @param Nodes\Literal $node
     */
    protected function visitLiteral(Literal $node)
    {
        $str = preg_replace('/\\n/', '\\\\n', $node->string);
        $this->buffer($str);
    }

    /**
     * @param Nodes\Block $block
     */
    protected function visitBlock(Block $block)
    {
        foreach ($block->nodes as $n) {
            $this->visit($n);
        }
    }

    /**
     * @param Nodes\Doctype $doctype
     *
     * @throws \Exception
     */
    protected function visitDoctype(Doctype $doctype = null)
    {
        $doc = (empty($doctype->value) || $doctype == null || !isset($doctype->value))
            ? 'default'
            : strtolower($doctype->value);

        $str = isset($this->doctypes[$doc])
            ? $this->doctypes[$doc]
            : "<!DOCTYPE {$doc}>";

        $this->buffer($str . $this->newline());

        $this->terse = (strtolower($str) == '<!doctype html>');

        $this->xml = ($doc == 'xml');
    }

    /**
     * @param Nodes\Mixin $mixin
     */
    protected function visitMixinBlock()
    {
        $name = var_export($this->visitedMixin->name, true);

        $code = $this->restrictedScope
            ? "\\Jade\\Compiler::callMixinBlock($name, \$attributes);"
            : "\$__varHandler = get_defined_vars(); \\Jade\\Compiler::callMixinBlockWithVars($name, \$__varHandler, \$attributes); extract(array_diff_key(\$__varHandler, array('__varHandler' => 1)));";

        $this->buffer($this->createCode($code));
    }

    /**
     * @param Nodes\Filter $node
     *
     * @throws \InvalidArgumentException
     */
    protected function visitFilter(Filter $node)
    {
        $filter = $this->getFilter($node->name);

        // Filters can be either a iFilter implementation, nor a callable
        if (is_string($filter) && class_exists($filter)) {
            $filter = new $filter();
        }
        if (!is_callable($filter)) {
            throw new \InvalidArgumentException($node->name . ': Filter must be callable');
        }
        $this->buffer($filter($node, $this));
    }

    /**
     * @param Nodes\Text $text
     */
    protected function visitText($text)
    {
        $this->buffer($this->interpolate($text->value));
    }

    /**
     * @param Nodes\Comment $comment
     */
    protected function visitComment(Comment $comment)
    {
        if ($comment->buffer) {
            $this->buffer('<!--' . $comment->value . '-->');
        }
    }

    /**
     * @param Nodes\BlockComment $comment
     */
    protected function visitBlockComment(BlockComment $comment)
    {
        if (!$comment->buffer) {
            return;
        }

        list($open, $close) = strlen($comment->value) && 0 === strpos(trim($comment->value), 'if')
            ? array('[' . trim($comment->value) . ']>', '<![endif]')
            : array($comment->value, '');

        $this->buffer('<!--' . $open);
        $this->visit($comment->block);
        $this->buffer($close . '-->');
    }

    /**
     * @param $node
     */
    protected function visitEach(Each $node)
    {
        //if (is_numeric($node->obj)) {
        //if (is_string($node->obj)) {
        //$serialized = serialize($node->obj);
        if (isset($node->alternative)) {
            $code = $this->createCode('if (isset(%s) && %s) {', $node->obj, $node->obj);
            $this->buffer($code);
            $this->indents++;
        }

        if (isset($node->key) && mb_strlen($node->key) > 0) {
            $code = $this->createCode('foreach (%s as %s => %s) {', $node->obj, $node->key, $node->value);
        } else {
            $code = $this->createCode('foreach (%s as %s) {', $node->obj, $node->value);
        }

        $this->buffer($code);

        $this->indents++;
        $this->visit($node->block);
        $this->indents--;

        $this->buffer($this->createCode('}'));

        if (isset($node->alternative)) {
            $this->indents--;
            $this->buffer($this->createCode('} else {'));
            $this->indents++;

            $this->visit($node->alternative);
            $this->indents--;

            $this->buffer($this->createCode('}'));
        }
    }

    /**
     * @param $attributes
     */
    protected function visitAttributes($attributes)
    {
        $this->tempPrettyPrint(false, 'compileAttributes', $attributes);
    }
}
