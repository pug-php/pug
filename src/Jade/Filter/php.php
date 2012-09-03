<?php

function php($str) {
	if (is_object($str)) {
		$new_str = '';
		foreach ($str->nodes as $n) {

            if (preg_match('/^[[:space:]]*\|(.*)/', $n->value, $m)) {
                $new_str = $m[1];
            }else{
			    $new_str .= $n->value . "\n";
            }
		}
		$str = $new_str;
	}
	return '<?php ' . $str . ' ?>';
}
