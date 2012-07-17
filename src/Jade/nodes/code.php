<?php

namespace Nodes;

class Code extends Node {
	public $value;
	public $buffer;
	public $escape;

	public function __construct($value,$buffer,$escape) {
		$this->value = $value;
		$this->buffer = $buffer;
		$this->escape = $escape;

		/*
		if (preg_match('/^ *else/', $value)) {
			$this->debug = true;
		}
		*/
	}
}
