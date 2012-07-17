<?php 

namespace Nodes;

class CaseNode extends Node {
	public $expr;
	public $block;

	public function __construct($expr, $block) {
		$this->expr = $expr;
		$this->block = $block;
	}
}
