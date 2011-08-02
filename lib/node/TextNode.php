<?php


class TextNode extends Node {

    protected $lines = array();

    /**
     * Initialize text node with string.
     *
     * @param   string|null $string text
     * @param   integer     $line   source line
     */
    public function __construct($string = null, $line) {
        parent::__construct($line);

        if ( !empty($string) ) {
            $this->lines = explode("\n", $string);
        }
    }

    /**
     * Add text line to node.
     *
     * @param   string  $line   string line
     */
    public function addLine($line) {
        $this->lines[] = $line;
    }

    /**
     * Return text lines.
     *
     * @return  array           array of strings
     */
    public function getLines() {
        return $this->lines;
    }
}
