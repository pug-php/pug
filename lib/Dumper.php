<?php

namespace lib;

use lib\node\Node;
use lib\node\BlockNode;
use lib\node\DoctypeNode;
use lib\node\TagNode;
use lib\node\TextNode;
use lib\node\FilterNode;
use lib\node\CommentNode;
use lib\node\CodeNode;

class Dumper {

    protected $doctypes = array(
        '5'             => '<!DOCTYPE html>',
        'xml'           => '<?xml version="1.0" encoding="utf-8" ?>',
        'default'       => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'transitional'  => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'strict'        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'frameset'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        '1.1'           => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        'basic'         => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">',
        'mobile'        => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" "http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">'
    );

    protected $selfClosing = array('meta', 'img', 'link', 'br', 'hr', 'input', 'area', 'base');

    protected $codes = array(
        "/^ *if[ \(]+.*\: *$/"        => 'endif',
        "/^ *else *\: *$/"            => 'endif',
        "/^ *else *if[ \(]+.*\: *$/"  => 'endif',
        "/^ *while *.*\: *$/"         => 'endwhile',
        "/^ *for[ \(]+.*\: *$/"       => 'endfor',
        "/^ *foreach[ \(]+.*\: *$/"   => 'endforeach',
        "/^ *switch[ \(]+.*\: *$/"    => 'endswitch',
        "/^ *case *.* *\: *$/"        => 'break'
    );

    protected $nextIsIf = array();

    /**
     * Dump node to string.
     *
     * @param   BlockNode   $node   root node
     *
     * @return  string
     */
    public function dump(BlockNode $node) {
        return $this->dumpNode($node);
    }

    /**
     * Dump node to string.
     *
     * @param   Node    $node   node to dump
     * @param   integer $level  indentation level
     *
     * @return  string
     */
    protected function dumpNode(Node $node, $level = 0) {
        $dumper = 'dump' . basename(str_replace('\\', '/', get_class($node)), 'Node');

        return $this->$dumper($node, $level);
    }

    /**
     * Dump block node to string.
     *
     * @param   BlockNode   $node   block node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpBlock(BlockNode $node, $level = 0) {
        $html = '';
        $last = '';

        $children = $node->getChildren();
        foreach ( $children as $i => $child ) {
            if ( !empty($html) && !empty($last) ) {
                $html .= "\n";
            }

            $this->nextIsIf[$level] = isset($children[$i + 1]) && ($children[$i + 1] instanceof CodeNode);
            $last  = $this->dumpNode($child, $level);
            $html .= $last;
        }

        return $html;
    }

    /**
     * Dump doctype node.
     *
     * @param   DoctypeNode $node   doctype node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpDoctype(DoctypeNode $node, $level = 0) {
        if ( !isset($this->doctypes[$node->getVersion()]) ) {
            throw new \Exception(sprintf('Unknown doctype %s', $node->getVersion()));
        }

        return $this->doctypes[$node->getVersion()];
    }

    /**
     * Dump tag node.
     *
     * @param   TagNode $node   tag node
     * @param   integer $level  indentation level
     *
     * @return  string
     */
    protected function dumpTag(TagNode $node, $level = 0) {
        $html = str_repeat('  ', $level);

        if ( in_array($node->getName(), $this->selfClosing) ) {
			$html .= sprintf('<%s%s />', $node->getName(), $this->dumpAttributes($node->getAttributes()));
			return $html;
        } else {
			$html .= sprintf('<%s%s>', $node->getName(), $this->dumpAttributes($node->getAttributes()));

            if ( $node->getCode() ) {
                if ( count($node->getChildren()) ) {
                    $html .= "\n" . str_repeat('  ', $level + 1) . $this->dumpCode($node->getCode());
                } else {
                    $html .= $this->dumpCode($node->getCode());
                }
            }
            if ( $node->getText() && count($node->getText()->getLines()) ) {
                if ( count($node->getChildren()) ) {
                    $html .= "\n" . str_repeat('  ', $level + 1) . $this->dumpText($node->getText());
                } else {
                    $html .= $this->dumpText($node->getText());
                }
            }

            if ( count($node->getChildren()) ) {
                $html .= "\n";
                $children = $node->getChildren();
                foreach ( $children as $i => $child ) {
                    $this->nextIsIf[$level + 1] = isset($children[$i + 1]) && ($children[$i + 1] instanceof CodeNode);
                    $html .= $this->dumpNode($child, $level + 1);
                }
                $html .= "\n" . str_repeat('  ', $level);
            }

            return $html.sprintf('</%s>', $node->getName());
        }
    }

    /**
     * Dump text node.
     *
     * @param   TextNode    $node   text node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpText(TextNode $node, $level = 0) {
        $indent = str_repeat('  ', $level);

        return $indent . $this->replaceHolders(implode("\n" . $indent, $node->getLines()), 'text');
    }

    /**
     * Dump comment node.
     *
     * @param   CommentNode $node   comment node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpComment(CommentNode $node, $level = 0) {
        if ( $node->isBuffered() ) {
            $html = str_repeat('  ', $level);

            if ( $node->getBlock() ) {
                $string = $node->getString();
                $beg    = "<!--\n";
                $end    = "\n" . str_repeat('  ', $level) . '-->';

                if ( preg_match('/^ *if/', $string) ) {
                    $beg = '<!--[' . $string . "]>\n";
                    $end = "\n" . str_repeat('  ', $level) . '<![endif]-->';
                    $string = '';
                }

                $html .= $beg;
                if ( $string !== '' ) {
                    $html .= str_repeat('  ', $level + 1) . $string . "\n";
                }
                $html .= $this->dumpBlock($node->getBlock(), $level + 1);
                $html .= $end;
            } else {
                $html = str_repeat('  ', $level) . '<!-- ' . $node->getString() . ' -->';
            }

            return $html;
        } else {
            return '';
        }
    }

    /**
     * Dump code node.
     *
     * @param   CodeNode    $node   code node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpCode(CodeNode $node, $level = 0) {
        $html = str_repeat('  ', $level);

        if ( $node->getBlock() ) {
            if ( $node->isBuffered() ) {
                $begin = '<?php echo '.$node->getCodeType().'($' . trim(preg_replace('/^ +/', '', $node->getCode())) . ") { ?>\n";
            } else {
                $begin = '<?php ' . preg_replace('/^ +/', '', $node->getCode()) . " { ?>\n";
            }
            $end = "\n" . str_repeat('  ', $level) . '<?php } ?>';

            foreach ( $this->codes as $regex => $ending ) {
                if ( preg_match($regex, $node->getCode()) ) {
                    $begin  = '<?php ' . preg_replace('/^ +| +$/', '', $node->getCode()) . " ?>\n";
                    $end    = "\n" . str_repeat('  ', $level) . '<?php ' . $ending . '; ?>';
                    if ( $ending === 'endif' && isset($this->nextIsIf[$level]) && $this->nextIsIf[$level] ) {
                        $end = '';
                    }
                    break;
                }
            }

            $html .= $begin;
            $html .= $this->dumpNode($node->getBlock(), $level + 1);
            $html .= $end;
        } else {
            if ( $node->isBuffered() ) {
                $html .= '<?php echo '.$node->getCodeType().'($' . preg_replace('/^ +/', '', $node->getCode()) . ') ?>';
            } else {
                $html .= '<?php ' . preg_replace('/^ +/', '', $node->getCode()) . ' ?>';
            }
        }

        return $html;
    }

    /**
     * Dump filter node.
     *
     * @param   FilterNode  $node   filter node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpFilter(FilterNode $node, $level = 0) {
        $text = '';
        if ( $node->getBlock() ) {
            $text = $this->dumpNode($node->getBlock(), $level + 1);
        }

        switch ( $node->getName() ) {
            case 'css':
                $opening_tag = '<style type="text/css">';
                $closing_tag = '</style>';
                break;
            case 'php':
                $opening_tag = '<?php';
                $closing_tag = '?>';
                break;
            case 'cdata':
                $opening_tag = '<![CDATA[';
                $closing_tag = ']]>';
                break;
            case 'javascript':
                $opening_tag = '<script type="text/javascript">';
                $closing_tag = '</script>';
                break;
        }

        $indent = str_repeat('  ', $level);

        $html  = $indent . $opening_tag . "\n";
        $html .= $text . "\n";
        $html .= $indent . $closing_tag;

        return $html;
    }

    /**
     * Dump attributes.
     *
     * @param   array   $attributes attributes associative array
     *
     * @return  string
     */
    protected function dumpAttributes(array $attributes) {
        $items = array();

        foreach ( $attributes as $key => $value ) {
            if ( is_array($value) ) {
                $items[] = $key . '="' . $this->replaceHolders(implode(' ', $value), 'attribute', $key) . '"';
            } elseif ( $value === true ) {
                $items[] = $key . '="' . $key . '"';
            } elseif ( $value !== false ) {
                $items[] = $key . '="' . $this->replaceHolders($value, 'attribute', $key) . '"';
            }
        }

        return count($items) ? ' ' . implode(' ', $items) : '';
    }

    protected function replaceHolders($string, $type = 'none', $key = '') {
		//TODO # id 
		//fixes replacement bugs and changes syntax as like jade original
		if ($type == 'attribute') {
			if (preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_>]*$/', $string)) {
				return sprintf('<?php echo jade\jade_text($%s) ?>', $string);
			}
			$string = trim($string, '\'\"');
			$string = preg_replace('/[\'\"]/', '', $string);
			if ($key == 'class') {
				$string = str_replace('.', '', $string);
			}
			if ($key == 'id') {
				$string = str_replace('#', '', $string);
			}
			$string = jade_text($string);
		}
        $string = preg_replace_callback('/([!#]){([a-zA-Z_][^}]*)}/', function($matches) {
			$map = array('#'=>'jade\jade_text', '!'=>'jade\jade_html');
			return sprintf('<?php echo %s($%s) ?>', $map[$matches[1]], implementDotNotation($matches[2]));
        }, $string);
		
		return $string;
    }
}

function implementDotNotation($expression) {
	$mark = 0;
	$identifiers = array();
	for ($at = 0; $at < mb_strlen($expression); $at ++) {
		if ($expression[$at] == '.') {
			$identifiers[] = substr($expression, $mark, $at - $mark);
			$mark = $at;
		}
	}
	$identifiers[] = substr($expression, $mark);
	return implode('', preg_replace('/^\.([a-zA-Z0-9_][a-zA-Z0-9_]*)$/', '->$1', $identifiers));
}
?>
