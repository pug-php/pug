<?php 

namespace Jade\Nodes;

class Mixin extends Node {
	public $name;
	public $arguments;
	public $block;
	public $attributes;
	public $call;

	public function __construct($name, $arguments, $block, $call) {
		$this->name = $name;
		$this->arguments = $arguments;
		$this->block = $block;
		$this->attributes = array();
		$this->call = $call;
	}
}
