<?php

namespace Jade\Compiler;

use Jade\Nodes\Tag;

abstract class TagVisitor extends Visitor
{
    /**
     * @param Nodes\Tag $tag
     */
    protected function visitTagAttributes(Tag $tag, $selfClosing = false)
    {
        $noSlash = (!$selfClosing || $this->terse);

        if (count($tag->attributes)) {
            $open = '<' . $tag->name;
            $close = $noSlash ? '>' : ' />';

            $this->buffer($this->indent() . $open, false);
            $this->visitAttributes($tag->attributes);
            $this->buffer($close . $this->newline(), false);

            return;
        }

        $htmlTag = '<' . $tag->name . ($noSlash ? '>' : ' />');

        $this->buffer($htmlTag);
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

        $this->visitTagAttributes($tag, $selfClosing);

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
}
