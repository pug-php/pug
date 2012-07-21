<?php

namespace Jade\Nodes;

class Block extends Node {
	public $isBlock = true;
	public $nodes = array();

	public function __construct($node=null) {
		if (null !== $node) {
			$this->push($node);
		}
	}

	public function replace($other) {
		$other->nodes = $this->nodes;
	}	

	public function push($node) {
		return array_push($this->nodes,$node);
	}

	public function isEmpty() {
		return 0 == count($this->nodes);
	}

	public function unshift($node) {
		return array_unshift($this->nodes, $node);
	}

	public function includeBlock() {
		foreach ($this->nodes as $node) {
			if ($node->yield) {
				return $node;
			}

			if ($node->textOnly) {
				continue;
			}

			if ($node->includeBlock) {
				$ret = $node->includeBlock();
			}
			elseif ($node->block && !$node->block->isEmpty()) {
				$ret = $node->block->includeBlock();
			}
		}

		return $ret;
	}

	/* php does deep copy by default
	public function __clone() {
	}*/
}
