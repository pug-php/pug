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
use Jade\Nodes\MixinBlock;
use Jade\Nodes\Node;
use Jade\Nodes\Tag;
use Jade\Nodes\When;

abstract class Visitor
{
    /**
     * @param Nodes\Node $node
     *
     * @return array
     */
    public function visit(Node $node)
    {
        // TODO: set debugging info
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
        $within = $this->withinCase;
        $this->withinCase = true;

        $this->switchNode = $node;
        $this->visit($node->block);

        if (!isset($this->switchNode)) {
            unset($this->switchNode);
            $this->indents--;

            $code = $this->createCode('}');
            $this->buffer($code);
        }
        $this->withinCase = $within;
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
        if (isset($this->hasCompiledDoctype)) {
            throw new \Exception('Revisiting doctype');
        }
        $this->hasCompiledDoctype = true;

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
    protected function visitMixinBlock(MixinBlock $mixinBlock)
    {
        $name = var_export($this->visitedMixin->name, true);
        $code = $this->createCode("\\Jade\\Compiler::callMixinBlock($name, \$attributes);");

        $this->buffer($code);
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTag(Tag $tag)
    {
        if (isset($tag->buffer)) {
            if (preg_match('`^[a-z][a-zA-Z0-9]+(?!\()`', $tag->name)) {
                $tag->name = '$' . $tag->name;
            }
            $tag->name = trim($this->createCode('echo ' . $tag->name . ';'));
        }
        if (!isset($this->hasCompiledDoctype) && 'html' == $tag->name) {
            $this->visitDoctype();
        }

        $selfClosing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;

        if ($tag->name == 'pre') {
            $pp = $this->prettyprint;
            $this->prettyprint = false;
        }

        $noSlash = (!$selfClosing || $this->terse);

        if (count($tag->attributes)) {
            $open = '<' . $tag->name;
            $close = $noSlash ? '>' : ' />';

            $this->buffer($this->indent() . $open, false);
            $this->visitAttributes($tag->attributes);
            $this->buffer($close . $this->newline(), false);
        } else {
            $htmlTag = '<' . $tag->name . ($noSlash ? '>' : ' />');

            $this->buffer($htmlTag);
        }

        if (!$selfClosing) {
            $this->indents++;
            if (isset($tag->code)) {
                $this->visitCode($tag->code);
            }
            $this->visit($tag->block);
            $this->indents--;

            $this->buffer('</' . $tag->name . '>');
        }

        if ($tag->name == 'pre') {
            $this->prettyprint = $pp;
        }
    }

    /**
     * @param Nodes\Filter $node
     *
     * @throws \InvalidArgumentException
     */
    protected function visitFilter(Filter $node)
    {
        // Check that filter is registered
        if (!array_key_exists($node->name, $this->filters)) {
            throw new \InvalidArgumentException($node->name . ': Filter doesn\'t exists');
        }

        $filter = $this->filters[$node->name];

        // Filters can be either a iFilter implementation, nor a callable
        if (is_string($filter)) {
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
        $pp = $this->prettyprint;
        $this->prettyprint = false;
        $items = array();
        $classes = array();
        $classesCheck = array();
        $quote = var_export($this->quote, true);

        foreach ($attributes as $attr) {
            $key = trim($attr['name']);
            if ($key === '&attributes') {
                $addClasses = '';
                if (count($classes) || count($classesCheck)) {
                    foreach ($classes as &$value) {
                        $value = var_export($value, true);
                    }
                    foreach ($classesCheck as $value) {
                        $statements = $this->createStatements($value);
                        $classes[] = $statements[0][0];
                    }
                    $addClasses = '$__attributes["class"] = ' .
                        'implode(" ", array(' . implode(', ', $classes) . ')) . ' .
                        '(empty($__attributes["class"]) ? "" : " " . $__attributes["class"]); ';
                    $classes = array();
                    $classesCheck = array();
                }
                $value = empty($attr['value']) ? 'attributes' : $attr['value'];
                $statements = $this->createStatements($value);
                $items[] = $this->createCode(
                    '$__attributes = ' . $statements[0][0] . ';' .
                    $addClasses .
                    '\\Jade\\Compiler::displayAttributes($__attributes, ' . $quote . ');');
            } else {
                $valueCheck = null;
                $value = trim($attr['value']);

                if ($this->isConstant($value, $key == 'class')) {
                    $value = trim($value, ' \'"');
                    if ($value === 'undefined') {
                        $value = 'null';
                    }
                } else {
                    $json = static::parseValue($value);

                    if ($json !== null && is_array($json) && $key == 'class') {
                        $value = implode(' ', $json);
                    } elseif ($key == 'class') {
                        if ($this->keepNullAttributes) {
                            $value = $this->createCode('echo (is_array($_a = %1$s)) ? implode(" ", $_a) : $_a', $value);
                        } else {
                            $statements = $this->createStatements($value);
                            $classesCheck[] = '(is_array($_a = ' . $statements[0][0] . ') ? implode(" ", $_a) : $_a)';
                            $value = 'null';
                        }
                    } elseif ($this->keepNullAttributes) {
                        $value = $this->createCode(static::UNESCAPED, $value);
                    } else {
                        $valueCheck = $value;
                        $value = $this->createCode(static::UNESCAPED, '$__value');
                    }
                }

                if ($key == 'class') {
                    if ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                        array_push($classes, $value);
                    }
                } elseif ($value == 'true' || $attr['value'] === true) {
                    $items[] = ' ' . $key . ($this->terse
                        ? ''
                        : '=' . $this->quote . $key . $this->quote
                    );
                } elseif ($value !== 'false' && $value !== 'null' && $value !== 'undefined') {
                    $items[] = is_null($valueCheck)
                        ? ' ' . $key . '=' . $this->quote . $value . $this->quote
                        : $this->createCode('if (\\Jade\\Compiler::isDisplayable($__value = %1$s)) { ', $valueCheck)
                            . ' ' . $key . '=' . $this->quote . $value . $this->quote
                            . $this->createCode('}');
                }
            }
        }

        if (count($classes)) {
            if (count($classesCheck)) {
                $classes[] = $this->createCode('echo implode(" ", array(' . implode(', ', $classesCheck) . '))');
            }
            $items[] = ' class=' . $this->quote . implode(' ', $classes) . $this->quote;
        } elseif (count($classesCheck)) {
            $item = $this->createCode('if("" !== ($__classes = implode(" ", array(' . implode(', ', $classesCheck) . ')))) {');
            $item .= ' class=' . $this->quote . $this->createCode('echo $__classes') . $this->quote;
            $items[] = $item . $this->createCode('}');
        }

        $this->prettyprint = $pp;

        $this->buffer(' ' . trim(implode('', $items)), false);
    }
}
