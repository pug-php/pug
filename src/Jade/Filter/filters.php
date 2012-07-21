<?php

$dir = dirname(__FILE__);
$files = glob($dir . '/*.php');

foreach ($files as $f) {
	require_once($f);
}
