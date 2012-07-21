<?php

namespace Jade;

require_once('Filter/filters.php');

class Compiler {

	protected $xml;
	protected $parentIndents;

	protected $buffer = array();
	protected $prettyprint = false;
	protected $terse = true;
	protected $indents = 0;

    protected $doctypes = array(
        '5'             => '<!DOCTYPE html>',
        'html'          => '<!DOCTYPE html>',
        'default'       => '<!DOCTYPE html>',
        'xml'           => '<?xml version="1.0" encoding="utf-8" ?>',
        'transitional'  => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'strict'        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'frameset'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        '1.1'           => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        'basic'         => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">',
        'mobile'        => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" "http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">'
    );

    protected $selfClosing = array('meta', 'img', 'link', 'input', 'source', 'area', 'base', 'col', 'br', 'hr');
	protected $phpKeywords = array('switch','case','default','endswitch','if','elseif','else','endif','while','endwhile','do','for','endfor','foreach','endforeach');

	public function __construct($prettyprint=false) {
		$this->prettyprint = $prettyprint;
	}
	
	public function compile($node) {
		$this->visit($node);
		return implode('', $this->buffer);
	}

    public function visit(Nodes\Node $node) {
		// TODO: set debugging info
		$this->visitNode($node);
		return $this->buffer;
    }

	protected function buffer($line) {
		array_push($this->buffer, $line);
	}

	protected function indent() {
		if ($this->prettyprint) {
			return str_repeat('  ', $this->indents);
		}
		return '';
	}

	protected function newline() {
		if ($this->prettyprint) {
			return "\n";
		}
		return '';
	}

	protected function addDollarSign($str) {
		$id_regex = "[a-zA-Z_][a-zA-Z0-9_]*";

		/*
		 * TODO: test this against:
		 *		arr[], arr[1], arr[var], arr['const']
		 *		ojb.method, obj.method(), obj.method(arg), obj.method('const'), obj.method(arg1,arg2)
		 */
		$add = function($str) use ($id_regex) {
			$separators = preg_split("/{$id_regex}/",$str,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

			if (count($separators) == 0) {
				return '$' . $str;
			}

			$out		= '$__=$'.substr($str,0,$separators[0][1]).';';
			$sep_offset = $separators[0][1];
			$offset_end = 0;

			// $sep[0] - the separator string due to PREG_SPLIT_OFFSET_CAPTURE flag
			// $sep[1] - the offset due to PREG_SPLIT_OFFSET_CAPTURE
			foreach ($separators as $i => $sep) {

				// get the offset
				if (isset($separators[$i+1])) {
					$offset_end = $separators[$i+1][1];
				}else{
					$offset_end = strlen($str);
				}

				if ($sep[0] == '.' || $sep[0] == '->') {
					$name = substr($str,$sep_offset+1,$offset_end);
					$out .= '$__=isset($__->' . $name . ') ? $__->' . $name . ' : $__[\''.$name.'\'];';
					$sep_offset = $offset_end;
				//}elseif (in_array(trim($sep[0]),array('()','(',')','[',']'))) {
				}else{
					$out .= $sep[0];
				}

			}
			return $out;
		};

		$out = '';
		// find ids without the dollar sign
		while (preg_match("/.*?({$id_regex})/", $str, $matches)) {
			$pos = strpos($str, $matches[1]);

			if (!in_array($matches[1], $this->phpKeywords) && ($pos === 0 || $str[$pos-1] != '$')) {
				$out .= substr($str,0,$pos);
				$str = substr($str,$pos);
				preg_match('/([a-zA-Z0-9_]+|\.|->)+/', $str, $call_chain);
				$out .= $add($call_chain[0]);
				$str = substr($str,mb_strlen($call_chain[0]));
			}else{
				$pos = $pos+mb_strlen($matches[1]);
				$out .= substr($str,0,$pos);
				$str = substr($str,$pos);
			}
		}
		return $out . $str;
	}

	protected function createCode($code) {
		$variables = array();
		$arguments = func_get_args();


		// dont add the trailing semicolon because it might be a end of block '}' or 'else'
		if (count($arguments)==1) {
			return '<?php '.$code.' ?>';
		}

		array_shift($arguments); // remove $code
		foreach ($arguments as $arg) {
			array_push($variables, $this->addDollarSign(trim($arg)));
		}

		/*
		 * We need to handle some complex constructs:
		 *   each key, var in obj.prop.arr:
		 *
		 * For the above, we cant know if "obj" and "prop" are of type array or object,
		 * so, we need to remove it from the foreach and find the right accessor:
		 *
		 * $__=$obj;
		 * $__=isset($__['prop'])?$__['prop']:$__->prop;
		 * $__=isset($__['arr'])?$__['arr']:$__->arr;
		 *
		 * Then we need to add the dollar sign into the variables names and build the foreach:
		 *
		 * foreach($__ as $key => $var):
		 *
		 * At last we need to add the php opening and closing tags
		 */
		if (count($variables) == 1) { // dont need call_user_func_array
			if (false === strpos($variables[0],';')) {
				$code_str = sprintf($code,$variables[0]);
			}else{
				$code_str = $variables[0] . sprintf($code,'$__');
			}
		}else{
			$placeholders= array();
			$assignments = array();

			$count=0;
			array_walk($variables,function($el) use ($count) {
				if (false === strpos($el,';')) {
					$count++;
				}
			});

			$rename_chain_holder=false;
			if ($count>1) {
				$rename_chain_holder=true;
			}

			$count=0;
			foreach ($variables as $c) {
				if (false === strpos($c,';')) {
					array_push($placeholders, $c);
				}else{
					if ($this->prettyprint) {
						array_push($assignments, $this->newline() . $this->indent());
					}

					if ($rename_chain_holder) {
						array_push($placeholders, '$_'.$count);
						array_push($assignments, $c . '$_'.$count.' = $__;');
						$count++;
					}else{
						array_push($placeholders, '$__');
						array_push($assignments, $c);
					}

				}
			}

			array_unshift($placeholders, $code); // add the format string to the placeholders

			$code_str = implode('', $assignments);
			$code_str .= $this->newline() . $this->indent();
			$code_str .= call_user_func_array('sprintf',$placeholders);
			$code_str .= $this->newline() . $this->indent();
		}

		return '<?php '.$code_str.' ?>';
	}

	protected function interpolate($text) {
		$ok = preg_match_all('/(\\\\)?([#!]){(.*?)}/', $text, $matches, PREG_SET_ORDER);

		if (!$ok) {
			return $text;
		}

		$i=1; // str_replace need a pass-by-ref
		foreach ($matches as $m) {

			// \#{dont_do_interpolation}
			if (mb_strlen($m[1]) == 0) {
				$code_str = $this->createCode('echo %s',$m[3]);
				$text = str_replace($m[0], $code_str, $text, $i);
			}
		}

		return $text;
	}

    protected function visitNode(Nodes\Node $node) {
		$fqn = get_class($node);
		$parts = explode('\\',$fqn);
		$name = $parts[count($parts)-1];
		$method = 'visit' . ucfirst(strtolower($name));
        return $this->$method($node);
    }

	protected function visitCase(Nodes\CaseNode $node) {
		$within = $this->whithinCase;
		$this->withinCase = true;

		$code = $this->createCode('switch (%s):',$node->expr);
		$this->buffer($this->indent() . $code . $this->newline());

		$this->indents++;
		$this->visit($node->block);
		$this->indents--;

		$code = $this->createCode('endswitch');
		$this->buffer($this->indent() . $code . $this->newline());
		$this->withinCase = $within;
	}

	protected function visitWhen(Nodes\When $node) {
		if ('default' == $node->expr) {
			$code = $this->createCode('default:');
			$this->buffer($this->indent() . $code . $this->newline());
		}else{
			$code = $this->createCode('case %s:',$node->expr);
			$this->buffer($this->indent() . $code . $this->newline());
		}

		$this->visit($node->block);

		$code = $this->createCode('break');
		$this->buffer($this->indent() . $code . $this->newline());
	}

	protected function visitLiteral(Nodes\Literal $node) {
		$str = preg_replace('/\\n/','\\\\n',$node->string);
		$this->buffer($str);
	}

	protected function visitBlock(Nodes\Block $block) {
		foreach ($block->nodes as $k => $n) {
			$this->visit($n);
		}
	}

	protected function visitDoctype(Nodes\Doctype $doctype=null) {
		if (isset($this->hasCompiledDoctype)) {
			throw new Excpetion ('Revisiting doctype');
		}
		$this->hasCompiledDoctype = true;

		if ($doctype == null || !isset($doctype->value)) {
			$doc = 'default';
		}else{
			$doc = $doctype->value;
		}

		$str = $this->doctypes[strtolower($doc)];
		$this->buffer($this->indent() . $str . $this->newline());

		if ($doc == '5' || $doc == 'html' || $doc == 'default') {
			$this->terse = true;
		}

		$this->xml = false;
		if ($doc == 'xml') {
			$this->xml = true;
		}
	}

	protected function visitMixin(Nodes\Mixin $mixin) {
		$name = preg_replace('/-/', '_', $mixin) . '_mixin';
		$arguments = $mixin->arguments;
		$block = $mixin->block;
		$attributes = $mixin->attribute;

		if ($mixin->call) {
			$this->buffer($name . '(' . implode(', ',$arguments) . ');');
		}else{
			$this->buffer('function ' . $name . '(' . implode(', ',$arguments) . '){');
			$this->visit($block);
			$this->buffer('}');
		}
	}

    protected function visitTag(Nodes\Tag $tag) {
		if (!isset($this->hasCompiledDoctype) && 'html' == $tag->name) {
			$this->visitDoctype();
		}

		$self_closing = (in_array(strtolower($tag->name), $this->selfClosing) || $tag->selfClosing) && !$this->xml;

		if (count($tag->attributes)) {
			$open = '';
			$close= '';

			if ($self_closing) {
				$open = '<' . $tag->name . ' ';
				$close = ($this->terse) ? '>' : '/>';
			}else{
				$open = '<' . $tag->name . ' ';
				$close = '>';
			}

			$this->buffer($this->indent() . $open);
			$this->visitAttributes($tag->attributes);
			$this->buffer($close . $this->newline());
		}else{
			$html_tag = '';

			if ($self_closing) {
				$html_tag = '<' . $tag->name . ' ' . (($this->terse) ? '>' : '/>');
			}else{
				$html_tag = '<' . $tag->name . '>';
			}

			$this->buffer($this->indent() . $html_tag . $this->newline());
		}

		if (!$self_closing) {
			$this->indents++;
            if (isset($tag->code)) {
				$this->visitCode($tag->code);
            }
			$this->visit($tag->block);
			$this->indents--;

            $this->buffer($this->indent() . '</'. $tag->name . '>' . $this->newline());
        }
    }

	protected function visitFilter(Nodes\Filter $node) {
		$filter = $node->name;

		// filter:
		if ($node->isASTFilter) {
			$str = $filter($node->block, $this, $node->attributes);
		// :filter
		}else{
			$str = '';
			foreach ($this->block->nodes as $n) {
				$str .= $n->value . "\n";
			}
			$str = $filter($str, $filter->attributes);
		}
		$this->buffer($this->indent() . $str . $this->newline());
    }

    protected function visitText(Nodes\Text $text) {
		$this->buffer($this->indent() . $this->interpolate($text->value) . $this->newline());
    }

    protected function visitComment(Nodes\Comment $comment) {
		if (!$comment->buffer) {
			return;
		}

		$this->buffer($this->indent() .'<!--' . $comment->value . '-->' . $this->newline());
    }

	protected function visitBlockComment(Nodes\BlockComment $comment) {
		if (!$comment->buffer) {
			return;
		}

		if (0 === strpos('if', trim($comment->value))) {
			$this->buffer($this->indent() .'<!--[' . trim($comment->value) . ']>');
			$this->visit($comment->block);
			$this->buffer($this->indent() .'<![endif]-->');
		}else{
			$this->buffer($this->indent() .'<!--' . $comment->value);
			$this->visit($comment->block);
			$this->buffer('-->');
		}
	}

    protected function visitCode(Nodes\Code $code) {

		if ($code->buffer) {

			if ($code->escape) {
				$this->buffer($this->indent() . $this->createCode('echo htmlspecialchars(%s)',$code->value) . $this->newline());
			}else{
				$this->buffer($this->indent() . $this->createCode('echo %s',$code->value) . $this->newline());
			}
		}else{

			// fix else, it needs to be in the same php block that closes the if
			$end = false;
			$index = count($this->buffer)-1;
			if (false !== strpos($this->buffer[$index], $this->createCode('}'))) {
				unset($this->buffer[$index]);
				$this->buffer($this->indent() . $this->createCode('} %s {',$code->value) . $this->newline());
			}else{
				$this->buffer($this->indent() . $this->createCode('%s {',$code->value) . $this->newline());
			}

		}

		if (isset($code->block)) {
			$this->indents++;
			$this->visit($code->block);
			$this->indents--;

			if (!$code->buffer) {
				$this->buffer($this->indent() . $this->createCode('}') . $this->newline());
			}
		}
    }

	protected function visitEach($node) {

		//if (is_numeric($node->obj)) {
		//if (is_string($node->obj)) {
		//$serialized = serialize($node->obj);
		if (isset($node->key) && mb_strlen($node->key) > 0) {
			$code = $this->createCode('foreach (%s as %s => %s) {',$node->obj,$node->key,$node->value);
		}else{
			$code = $this->createCode('foreach (%s as %s) {',$node->obj,$node->value);
		}

		$this->buffer($this->indent() . $code . $this->newline());

		$this->indents++;
		$this->visit($node->block);
		$this->indents--;

		$this->buffer($this->indent() . $this->createCode('}') . $this->newline());
	}
    
    protected function visitAttributes($attributes) {
        $items = array();
        $classes = array();

        foreach ($attributes as $attr) {
			$key = trim($attr['name']);
			$value = trim($attr['value'],' \'\"');

			if ($key == 'class') {
				array_push($classes, $value);
			}
			else
            if ( is_array($value) ) {
                //$items[] = $key . '="' . trim($this->replaceHolders(implode(' ', $value), 'attribute', $key)) . '"';
			}
			else // trim() converts bool into string, use $attr['value'] insted of $value here
			if ($value == '' || $value == null || $attr['value'] === true) {
				if ($this->terse) {
					$items[] = $key;
				}else{
					$items[] = "{$key}='{$key}'";
				}
			}else{
				$items[] = "{$key}='{$value}'";
            }
        }

		if (count($classes)) {
			$items[] = 'class=\'' . implode(' ', $classes) . '\'';
		}

		$this->buffer(implode(' ', $items));
    }
}
