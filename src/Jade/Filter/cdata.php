<?php

function cdata($str) {
	return '<!CDATA[\\n' + $str + '\\n]]>';
}
