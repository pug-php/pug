<?php 

namespace Nodes;

class Filter extends Node {
	public $name;
	public $block;
	public $attributes;
	public $isASTFilter;

	public function __construct($name, $block, $attributes) {
		$this->name = $name;
		$this->block = $block;
		$this->attributes = $attributes;

		$this->isASTFilter = false;
		foreach ($block->nodes as $n) {
			if (false == $node->isText) {
				$this->isASTFilter = true;
				break;
			}
		}
	}
}
