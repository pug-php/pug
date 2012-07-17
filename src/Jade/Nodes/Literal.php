<?php 

namespace Nodes;

class Literal extends Node {
	public $str;

	public function __construct($str) {
		// escape the chars '\', '\n', '\r\n' and "'"
		$this->str = preg_replace( array('/\\\\/','/\n|\r\n/','/\'/'), array('\\\\',"\\n","\\'"), $str);
	}
}
