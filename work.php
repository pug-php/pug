<?php
mb_internal_encoding('utf-8');


function splitIntoLines($bytes) {
	return preg_split('/\n/', $bytes);
}

function length($bytes) {
	return mb_strlen($bytes);
}


class JP {
	protected $page;
	function reduce($bytes) {
		$this->page = mb_substr($this->page, mb_strlen($bytes));
	}
	function adjustLineDelimiter($bytes) {
		$this->page = preg_replace('/\r\n|\r/', '\n', $bytes);
	}
}

//class Jade {}

class iNode {
	private $properties = array();

	function __get($key) {
		return $this->properties[$key];
	}

	function __set($key, $value) {
		$this->properties[$key] = $value;
	}

	function setAttribute($a, $b) {
	}
}

//class Collection {}

?>
