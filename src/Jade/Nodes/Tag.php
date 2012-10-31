<?php

namespace Jade\Nodes;

$inline_tags = array(
    'a'
    ,'abbr'
    ,'acronym'
    ,'b'
    ,'br'
    ,'code'
    ,'em'
    ,'font'
    ,'i'
    ,'img'
    ,'ins'
    ,'kbd'
    ,'map'
    ,'samp'
    ,'small'
    ,'span'
    ,'strong'
    ,'sub'
    ,'sup'
);

class Tag extends Attributes {
    public $name;
    public $attributes;
    public $block;
    public $selfClosing = false;

    public function __construct($name, $block=null) {
        $this->name = $name;

        if ($block !== null) {
            $this->block = $block;
        }else{
            $this->block = new Block();
        }

        $this->attributes = array();
    }

    public function isInline() {
        return in_array($this->name, $inline_tags);
    }

    public function canInline() {
        $nodes = $this->block->nodes;

        $isInline = function($node) use (&$isInline) {
            if ($node->isBlock) {
                foreach ($node->nodes as $n) {
                    if (!$isInline($n)) {
                        return false;
                    }
                }
                return true;
            }

            if ($node->isText) {
                return true;
            }

            if (isset($node->isInline) && $node->isInline()) {
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
                if ($prev !== null && $nodes[$prev]->isText && $n->isText) {
                    return false;
                }
                $prev = $k;
            }
            return true;
        }

        return false;
    }
}
