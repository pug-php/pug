<?php

namespace Jade\Compiler;

use Jade\Nodes\Tag;

abstract class TagVisitor extends Visitor
{
    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTagAttributes(Tag $tag, $newLinePrettyPrint, $close = '>')
    {
        $open = '<' . $tag->name;
        $close = $this->getClassesDisplayCode() . $close;

        if (count($tag->attributes)) {
            $this->buffer($this->indent() . $open, false);
            $this->visitAttributes($tag->attributes);
            $this->buffer($close . $this->newline(), false);

            return;
        }

        $this->buffer($open . $close, $newLinePrettyPrint ? null : false);
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
    protected function compileTag(Tag $tag)
    {
        $selfClosing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;
        $this->visitTagAttributes($tag, $this->prettyprint, (!$selfClosing || $this->terse) ? '>' : ' />');

        if (!$selfClosing) {
            $this->visitTagContents($tag);
            $this->buffer('</' . $tag->name . '>');
        }
    }

    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTag(Tag $tag)
    {
        $this->initTagName($tag);

        $insidePrettyprint = !$tag->canInline() && $this->prettyprint && !$tag->isInline();
        $prettyprint = $tag->keepWhiteSpaces() || $insidePrettyprint;

        if ($this->prettyprint && !$insidePrettyprint) {
            $this->buffer[] = $this->indent();
        }

        $this->tempPrettyPrint($prettyprint, 'compileTag', $tag);

        if (!$prettyprint && $this->prettyprint && !$tag->isInline()) {
            $this->buffer[] = $this->newline();
        }
    }
}
