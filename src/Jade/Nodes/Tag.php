<?php

namespace Jade\Nodes;

class Tag extends Attributes
{
    protected static $inlineTags = array(
        'a',
        'abbr',
        'acronym',
        'b',
        'br',
        'code',
        'em',
        'font',
        'i',
        'img',
        'ins',
        'kbd',
        'map',
        'samp',
        'small',
        'span',
        'strong',
        'sub',
        'sup',
    );
    protected static $whiteSpacesTags = array(
        'pre',
        'script',
        'textarea',
    );
    public $name;
    public $attributes;
    public $block;
    public $selfClosing = false;

    public function __construct($name, $block = null)
    {
        $this->name = $name;

        $this->block = ($block !== null)
            ? $block
            : new Block();

        $this->attributes = array();
    }

    public function isInline()
    {
        return in_array($this->name, static::$inlineTags);
    }

    public function keepWhiteSpaces()
    {
        return in_array($this->name, static::$whiteSpacesTags);
    }

    public function canInline()
    {
        $nodes = $this->block->nodes;

        $isInline = function ($node) use (&$isInline) {
            if (isset($node->isBlock) && $node->isBlock) {
                foreach ($node->nodes as $n) {
                    if (!$isInline($n)) {
                        return false;
                    }
                }

                return true;
            }

            if (isset($node->isText) && $node->isText) {
                return true;
            }

            if (method_exists($node, 'isInline') && $node->isInline()) {
                return true;
            }

            return false;
        };

        if (count($nodes) == 0) {
            return true;
        }

        if (count($nodes) == 1) {
            return $isInline($nodes[0]);
        }

        $ret = true;
        foreach ($nodes as $n) {
            if (!$isInline($n)) {
                $ret = false;
                break;
            }
        }

        if ($ret) {
            $prev = null;
            foreach ($nodes as $k => $n) {
                if ($prev !== null && isset($nodes[$prev]->isText) && $nodes[$prev]->isText && isset($n->isText) && $n->isText) {
                    return false;
                }
                $prev = $k;
            }

            return true;
        }

        return false;
    }
}
