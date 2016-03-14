<?php

namespace Jade\Compiler;

use Jade\Nodes\Tag;

abstract class TagVisitor extends Visitor
{
    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTagAttributes(Tag $tag, $close = '>')
    {
        $open = '<' . $tag->name;

        if (count($tag->attributes)) {
            $this->buffer($this->indent() . $open, false);
            $this->visitAttributes($tag->attributes);
            $this->buffer($close . $this->newline(), false);

            return;
        }

        $this->buffer($open . $close);
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function initTagName(Tag $tag)
    {
        if (isset($tag->buffer)) {
            if (preg_match('`^[a-z][a-zA-Z0-9]+(?!\()`', $tag->name)) {
                $tag->name = '$' . $tag->name;
            }
            $tag->name = trim($this->createCode('echo ' . $tag->name . ';'));
        }
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTagContents(Tag $tag)
    {
        $this->indents++;
        if (isset($tag->code)) {
            $this->visitCode($tag->code);
        }
        $this->visit($tag->block);
        $this->indents--;
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTag(Tag $tag)
    {
        $this->initTagName($tag);

        $selfClosing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;

        $prettyprint = (
            $tag->keepWhiteSpaces() ||
            (!$tag->canInline() && $this->prettyprint && !$tag->isInline())
        );

        $visitor = $this;
        $this->tempPrettyPrint($prettyprint, function () use ($visitor, $tag, $selfClosing) {
            $visitor->visitTagAttributes($tag, (!$selfClosing || $visitor->terse) ? '>' : ' />');

            if (!$selfClosing) {
                $visitor->visitTagContents($tag);
                $visitor->buffer('</' . $tag->name . '>');
            }
        });

        if (!$prettyprint && $this->prettyprint && !$tag->isInline()) {
            $this->buffer($this->newline());
        }
    }
}
