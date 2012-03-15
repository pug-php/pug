<?php
mb_internal_encoding('utf-8');

class JP {
	protected $page;
	private $last;
	function reduce($bytes) {
		$this->page = mb_substr($this->page, mb_strlen($bytes));
	}
	function length() {
		return mb_strlen($this->page);
	}
	function adjustLineDelimiter($bytes) {
		$this->page = preg_replace('/\r\n|\r/', '\n', $bytes);
	}
	function next($type) {
		$map = array(
			'DOCUMENT_TYPE'=>'/^(?:!!!|doctype) *(\w+)?/',
			'TAG'=>'/^(\w[\w:-]*\w)|^(\w[\w-]*)/',
			'FILTER'=>'/^:(\w+)/',
			'SCRIPT'=>'/^(!?=|-)([^\n]+)/',
			'ID'=>'/^#([\w-]+)/',
			'CLASS'=>'/^\.([\w-]+)/',
			'ATTRIBUTE'=>''
				.'/^([\(])\s*' /* OPENER */
				.'|^\s*([\)])' /* CLOSER */
				.'|^[\t ]*([,\n])[\t ]*' /* DELIMITER */
				.'|^([\w:-]+)[\t ]*=[\t ]*([\w.]+)' /* VARIABLE */
				.'|^([\w:-]+)[\t ]*=[\t ]*(\'[^\']*\')' /* SINGLE_QUOTE */
				.'|^([\w:-]+)[\t ]*=[\t ]*(\"[^\"]*\")' /* DOUBLE_QUOTE */
				.'|^([\w:-]+)/', /* _NONE */
			'NEXT'=>'/^(:  *)|^\n( *)/',
			'TEXT'=>'/^(?:\| ?)?([^\n]+)/',
			'dotBlock' => '/^(\.\n)(\s+)(.+)(\n\2.+)*(\n|$)(\s*)/m',
		);

		if (preg_match($map[$type], $this->page, $parentheses)) {
			$this->reduce($parentheses[0]);
			return $this->item($type, $parentheses);
		}
	}
	function item($type, $data) {
//		echo sprintf('%-16s  %s'.PHP_EOL, $type, trim($data[0]));
		$remap= array(
			'DOCUMENT_TYPE'=>'doctype',
			'TAG'=>'tag',
			'FILTER'=>'filter',
			'SCRIPT'=>'code',
			'ID'=>'id',
			'CLASS'=>'class',
			'TEXT'=>'text',
			'ATTRIBUTE'=>'attributes',
			'dotBlock' => 'text',
		);

		$index = 0;
		if ($type == 'DOCUMENT_TYPE') {
			$index = 1;
		}
		if ($type == 'TEXT') {
			$index = 1;
		}
		if ($type == 'ATTRIBUTE') {
			return $data;
		}
		if ($type === 'dotBlock'){
			$data[0] = ltrim($data[0], '.');
		}
		return (object) array('type'=>$remap[$type], 'value'=>$data[$index]);
	}
}
?>
