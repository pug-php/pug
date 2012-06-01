<?php

namespace Jade;

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
	
    protected $specialAttribtues = array('selected', 'checked', 'required', 'disabled');

    protected $nextIsIf = array();

    public static function _text($bytes) {
        $patterns = array('/&(?!\w+;)/', '/</', '/>/', '/"/');
        $replacements = array('&amp;', '&lt;', '&gt;', '&quot;');
        return preg_replace($patterns, $replacements, $bytes);
    }

    public static function _html($bytes) {
        return $bytes;
    }

    /**
     * Dump node to string.
     *
     * @param   BlockNode   $node   root node
     *
     * @return  string
     */
    public function dump(Node $node) {
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
//        $dumper = 'dump' . basename(str_replace('\\', '/', get_class($node)), 'Node');
		$dumper = 'dump' .$node->type;
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
    protected function dumpBlock(Node $node, $level = 0) {
        $html = '';
        $last = '';

        $children = $node->children;
        foreach ( $children as $i => $child ) {
            if ( !empty($html) && !empty($last) ) {
                $html .= "\n";
            }

            $this->nextIsIf[$level] = isset($children[$i + 1]) && ($children[$i + 1] instanceof Node);
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
    protected function dumpDoctype(Node $node, $level = 0) {
        if ( !isset($this->doctypes[$node->version]) ) {
            throw new \Exception(sprintf('Unknown doctype %s', $node->version));
        }

        return $this->doctypes[$node->version];
    }

    /**
     * Dump tag node.
     *
     * @param   TagNode $node   tag node
     * @param   integer $level  indentation level
     *
     * @return  string
     */
    protected function dumpTag(Node $node, $level = 0) {
        $html = str_repeat('  ', $level);

        if ( in_array($node->name, $this->selfClosing) ) {
			$html .= sprintf('<%s%s />', $node->name, $this->dumpAttributes($node->attributes));
			return $html;
        } else {
			$html .= sprintf('<%s%s>', $node->name, $this->dumpAttributes($node->attributes));

            if ( $node->code ) {
                if ( count($node->children) ) {
                    $html .= "\n" . str_repeat('  ', $level + 1) . $this->dumpCode($node->code);
                } else {
                    $html .= $this->dumpCode($node->code);
                }
            }
            if ( $node->text && count($node->text->lines) ) {
                if ( count($node->children) ) {
                    $html .= "\n" . str_repeat('  ', $level + 1) . $this->dumpText($node->text);
                } else {
                    $html .= $this->dumpText($node->text);
                }
            }

            if ( count($node->children) ) {
                $html .= "\n";
                $children = $node->children;
                foreach ( $children as $i => $child ) {
                    $this->nextIsIf[$level + 1] = isset($children[$i + 1]) && ($children[$i + 1] instanceof Node);
                    $html .= $this->dumpNode($child, $level + 1);
                }
                $html .= "\n" . str_repeat('  ', $level);
            }

            return $html.sprintf('</%s>', $node->name);
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
    protected function dumpText(Node $node, $level = 0) {
        $indent = str_repeat('  ', $level);

        return $indent . $this->replaceHolders(implode("\n" . $indent, $node->lines), 'text');
    }

    /**
     * Dump comment node.
     *
     * @param   Node $node   comment node
     * @param   integer     $level  indentation level
     *
     * @return  string
     */
    protected function dumpComment(Node $node, $level = 0) {
        if ( $node->buffering ) {
            $html = str_repeat('  ', $level);

            if ( $node->block ) {
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
                $html .= $this->dumpBlock($node->block, $level + 1);
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
    protected function dumpCode(Node $node, $level = 0) {
        $html = str_repeat('  ', $level);

		$map = array('='=>'Jade\Dumper::_text', '!='=>'Jade\Dumper::_html');


        if ( $node->block ) {
            if ( $node->buffering ) {
                $begin = '<?php echo '.$map[$node->codeType].'($' . trim(preg_replace('/^ +/', '', $node->code)) . ") { ?>\n";
            } else {
                $begin = '<?php ' . preg_replace('/^ +/', '', $node->code) . " { ?>\n";
            }
            $end = "\n" . str_repeat('  ', $level) . '<?php } ?>';

            foreach ( $this->codes as $regex => $ending ) {
                if ( preg_match($regex, $node->code) ) {
                    $begin  = '<?php ' . preg_replace('/^ +| +$/', '', $node->code) . " ?>\n";
                    $end    = "\n" . str_repeat('  ', $level) . '<?php ' . $ending . '; ?>';
                    if ( $ending === 'endif' && isset($this->nextIsIf[$level]) && $this->nextIsIf[$level] ) {
                        $end = '';
                    }
                    break;
                }
            }

            $html .= $begin;
            $html .= $this->dumpNode($node->block, $level + 1);
            $html .= $end;
        } else {
            if ( $node->buffering ) {
                $html .= '<?php echo '.$map[$node->codeType].'(' . preg_replace('/^ +/', '', $node->code) . ') ?>';
            } else {
                $html .= '<?php ' . preg_replace('/^ +/', '', $node->code) . ' ?>';
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
    protected function dumpFilter(Node $node, $level = 0) {
        $text = '';
        if ( $node->block ) {
            $text = $this->dumpNode($node->block, $level + 1);
        }
        switch ( ltrim($node->name, ':') ) {
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
                $items[] = $key . '="' . trim($this->replaceHolders(implode(' ', $value), 'attribute', $key)) . '"';
            } elseif ( $value === true ) {
                $items[] = $key . '="' . trim($key) . '"';
            } elseif (in_array($key, $this->specialAttribtues) && preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_>]*$/', $value)) {
                $items[] = trim($this->replaceHolders($value, 'attribute', $key));
            } elseif ( $value !== false ) {
                $items[] = $key . '="' . trim($this->replaceHolders($value, 'attribute', $key)) . '"';
            }
        }

        return count($items) ? ' ' . implode(' ', $items) : '';
    }

    protected function replaceHolders($string, $type = 'none', $key = '') {
		//fixes replacement bugs and changes syntax as like jade original
		if ($type == 'attribute') {
			if (preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_>]*$/', $string)) {
				if (in_array($key, $this->specialAttribtues)) {
					return sprintf('<?php if ($%s) echo "%s=\'".Jade\Dumper::_text($%s)."\'"; ?>', $string, $key, $string);
				}
				return sprintf('<?php echo Jade\Dumper::_text($%s); ?>', $string);
			}
			$string = trim($string, '\'\"');
			if ($key === 'class') {
				$string = str_replace('.', '', $string);
			}
			if ($key === 'id' && strpos($string, '#{') === false) {
				$string = str_replace('#', '', $string);
			}
			// If it doesn't look like php we can run it through dump_text
			if ( strpos($string, '(') === false && strpos($string, ')') === false && strpos($string, '::') === false && strpos($string, '->') === false){
				$string = self::_text($string);
			}
		}
        $string = preg_replace_callback('/([!#]){([^}]+)}/', function($matches) {
			$map = array('#'=>'Jade\Dumper::_text', '!'=>'Jade\Dumper::_html');
			return sprintf('<?php echo %s(%s); ?>', $map[$matches[1]], $matches[2]);
        }, $string);
		
		return $string;
    }
}
?>
