<?php

namespace Jade;

class Node {

    protected $string;
  	public $codeType;
    public $type;
    public $block;
    public $buffering = false;
    public $children = array();
    public $version;
    public $lines = array();
    public $name;
    public $attributes = array('id' => false);
    public $text;
    public $code;

	public function __construct($type) {
		$this->type = $type;

		if ($type == 'tag') {
			$this->name = func_get_arg(1);
		}
		if ($type == 'doctype') {
			$this->version = func_get_arg(1);
		}
		if ($type == 'text') {
			$string = func_get_arg(1);
			if ( !empty($string) ) {
				$this->lines = explode("\n", $string);
			}
		}
		if ($type == 'code') {
			$this->code         = func_get_arg(1);
			$this->buffering    = false;
			if (func_num_args() > 1) {
				$this->buffering = func_get_arg(2);
			}
		}
		if ($type == 'comment') {
			$this->string         = func_get_arg(1);
			$this->buffering    = false;
			if (func_num_args() > 1) {
				$this->buffering = func_get_arg(2);
			}
		}
		if ($type == 'filter') {
			$this->name         = func_get_arg(1);
			$this->attributes   = array();
			if (func_num_args() > 1) {
				$this->attributes  = func_get_arg(2);
			}
		}
	}

    public function addChild(Node $node) {
        $this->children[] = $node;
    }

    public function addChildren(Array $nodes) {
        $this->children = array_merge($this->children, $nodes);
    }

    public function setAttribute($key, $value) {
        if ( $key === 'class' ) {
            if ( !isset($this->attributes[$key]) ) {
                $this->attributes[$key] = array();
            }

            $this->attributes[$key][] = $value;
        } else {
            $this->attributes[$key]  = $value;
        }
    }

    public function getString() {
        return $this->string;
    }
    public function addLine($line) {
        $this->lines[] = $line;
    }
}

?>
