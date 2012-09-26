<?php

function cdata($str) {
    if (is_object($str)) {
		$new_str = '';
		foreach ($str->nodes as $n) {
			//TODO: original doing interpolation here
			$new_str .= $n->value . "\n";
		}
		$str = $new_str;
	}
	return "<!CDATA[\n" . $str . "\n]]>";
}
