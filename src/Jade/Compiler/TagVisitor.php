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

        if (!isset($this->hasCompiledDoctype) && 'html' == $tag->name) {
            $this->visitDoctype();
        }

        $selfClosing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;

        if ($isPreTag = ($tag->name == 'pre')) {
            $prettyprint = $this->prettyprint;
            $this->prettyprint = false;
        }

        $this->visitTagAttributes($tag, (!$selfClosing || $this->terse) ? '>' : ' />');

        if (!$selfClosing) {
            $this->visitTagContents($tag);

            $this->buffer('</' . $tag->name . '>');
        }

        if ($isPreTag) {
            $this->prettyprint = $prettyprint;
        }
    }
}
