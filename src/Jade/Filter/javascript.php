<?php


function javascript($str) {
	if (is_object($str)) {
		$new_str = '';
		foreach ($str->nodes as $n) {
			$new_str .= $n->value . "\n";
		}
		$str = $new_str;
	}
	return '<script type="text/javascript">' . $str . '</script>';
}
