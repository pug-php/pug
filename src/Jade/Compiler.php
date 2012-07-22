<?php

namespace Jade;

require_once('Filter/filters.php');

class Compiler {

	protected $xml;
	protected $parentIndents;

	protected $buffer = array();
	protected $prettyprint = false;
	protected $terse = true;
	protected $withinCase = false;
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
	protected $phpKeywords = array('true','false','null','switch','case','default','endswitch','if','elseif','else','endif','while','endwhile','do','for','endfor','foreach','endforeach');

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

    protected function buffer($line, $indent=null) {
        if (($indent !== null && $indent == true) || ($indent === null && $this->prettyprint)) {
		    array_push($this->buffer, $this->indent() . $line . $this->newline());
        }else{
		    array_push($this->buffer, $line);
        }
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

    protected function isConstant($str) {
        // pattern without escaping for php:
        //      /^[ \t]*(([\'\"])(?:\\.|[^\'\"\\])*\1|true|false|null)[ \t]*$/
        //
        //      [ \t] - space
        //
        //      subpatterns:
        //          [\'\"] - matches a string opening
        //          \\. - matches any escaped character 
        //          [^\'\"\\] - matches everythin, except the escape char and string ending
        //          \2 - matches the same char used for opening the string
        //
        //          true|false|null - keywords
        $ok = preg_match_all('/^[ \t]*(([\'"])(?:\\\\.|[^\'"\\\\])*\2|true|false|null)[ \t]*$/', $str);

        return $ok>0 ? true : false;
    }

    /**
     * Add the dollar sign in front of identifiers, and guess the right accessor '[]' or '->' for a given
     * property or array index.
     *
     * Todo: Handle better function calls and arrays accesses
     */
	protected function addDollarSign($str) {
		$id_regex = "[a-zA-Z_][a-zA-Z0-9_]*";

		$add = function($str) use ($id_regex) {
			$separators = preg_split("/{$id_regex}/",$str,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

			if (count($separators) == 0) {
				return '$' . $str;
			}

			$out		= '$__=$'.substr($str,0,$separators[0][1]).';';
			$sep_offset = $separators[0][1];
			$offset_end = 0;
            $states     = array();

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
                 }else{
					$out .= $sep[0];
				}

			}
			return $out;
		};

		$out = '';
        $call=false;
		// find ids without the dollar sign
		while (preg_match("/.*?({$id_regex})/", $str, $matches)) {
            $pos = strpos($str, $matches[1]);

            // not a keyword
            // doesnt have $ yet
            if (!in_array($matches[1], $this->phpKeywords) && ($pos === 0 || $str[$pos-1] != '$')) {
				$out .= substr($str,0,$pos);
				$str = substr($str,$pos);

				preg_match('/([a-zA-Z0-9_]+|\.|->)+/', $str, $call_chain);

				$out .= $add($call_chain[0]);
				$str = substr($str,mb_strlen($call_chain[0]));

                // hack for function call
                // this will add the placeholder at the end of the out string, in the following 
                // interaction the parenthesis will be added and the function will be called
                // from the placeholder reference
                if ($str[0] == '(') {
                    $out .= '$__=$__';
                    $call = true;
                }
            }else{
				$pos = $pos+mb_strlen($matches[1]);
				$out .= mb_substr($str,0,$pos);
				$str = mb_substr($str,$pos);
			}

            // hack for funciton call
            // this will add the semicolon after the function call
            if ($call && mb_strlen($str) && $str[0] == ')') {
                $out .= ');';
                // remove the parenthesis from the $str since we already used it
                $pos--; 
                $str = mb_substr($str,1);
            }
        }
		return $out . $str;
	}

	protected function createCode($code) {
		$variables = array();
		$arguments = func_get_args();

		// dont add the trailing semicolon because it might be a end of block '}' or '} else {'
		if (count($arguments)==1) {
			return '<?php '.$code.' ?>';
		}

        $handle_string_and_code = function($code,$str,$res) {
            // no id means we might have as separator:
            //      a list of arguments     - a,b,c
            //      a function call         - a(b)
            //      a array access          - a[b]
            //      just a empty code part  - 'string'
            //
            // in theses cases we do not concatenate
            $test_ids = preg_match('/[a-zA-Z_][a-zA-Z0-9_]*/', $code);

            if (!$test_ids) {
                return $res . $code . $str;
            }

            $code = str_replace('+','.',$code);
            $code = $this->addDollarSign(trim($code));
            
            // the code expanded to a variable access, so, we need to set $__ to 
            // the right value
            if (strpos($code,';')) {
                // remove '.' if there is one, because we are moving code to the begining
                $code= trim($code,' .'); 

                if (strlen($str)) {
                    $res = $code . '$__=' . $res . '. $__ .' . $str . ';';
                }else{
                    $res = $code . '$__=' . $res . '. $__;';
                }
            }else{
                $res .= $code . $str;
            }

            return $res;
        };

		array_shift($arguments); // remove $code
		foreach ($arguments as $arg) {

            // shortcut for constants
            if ($this->isConstant($arg)) {
                array_push($variables, $arg);
                continue;
            }

            $test_string = preg_match_all('/([\'"])(?:\\\\.|[^\'"\\\\])*\1/', $arg, $matches, PREG_SET_ORDER);

            // there are no strings
            if (!$test_string) { 
			    array_push($variables, $this->addDollarSign(trim($arg)));
                continue;
            }

            // handle string - this handles mostly attributes like ".span(class='const-' + var)"
            $result = '';
            foreach ($matches as $m) {
                $string     = $m[0];
                $pos        = mb_strpos($arg, $m[0]);
                $code_part  = mb_substr($arg,0,$pos);

                $result = $handle_string_and_code($code_part, $string, $result);    

                // consume the arg
                $arg = mb_substr($arg,$pos+mb_strlen($m[0]));
            }

            // consumed all intercalated strings, means we have code left in the end
            if (mb_strlen($arg)) {
                $result = $handle_string_and_code($arg, '', $result);    
            }

            array_push($variables, $result);
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
                if ($m[2] == '!') {
				    $code_str = $this->createCode('echo %s',$m[3]);
                }else{
				    $code_str = $this->createCode('echo htmlspecialchars(%s)',$m[3]);
                }
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

	protected function visitCasenode(Nodes\CaseNode $node) {
		$within = $this->withinCase;
		$this->withinCase = true;

        // TODO: fix the case hack
        // php expects that the first case statement will be inside the same php block as the switch
        $code_str = 'switch (%s) { '.$this->newline().$this->indent().'case "__phphackhere__": break;';
		$code = $this->createCode($code_str,$node->expr);
		$this->buffer($code);

		$this->indents++;
		$this->visit($node->block);
		$this->indents--;

		$code = $this->createCode('}');
		$this->buffer($code);
		$this->withinCase = $within;
	}

	protected function visitWhen(Nodes\When $node) {
		if ('default' == $node->expr) {
			$code = $this->createCode('default:');
			$this->buffer($code);
		}else{
			$code = $this->createCode('case %s:',$node->expr);
			$this->buffer($code);
		}

		$this->visit($node->block);

		$code = $this->createCode('break;');
		$this->buffer( $code . $this->newline());
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
		$this->buffer( $str . $this->newline());

		if ($doc == '5' || $doc == 'html' || $doc == 'default') {
			$this->terse = true;
		}

		$this->xml = false;
		if ($doc == 'xml') {
			$this->xml = true;
		}
	}

	protected function visitMixin(Nodes\Mixin $mixin) {
		$name = preg_replace('/-/', '_', $mixin->name) . '_mixin';
		$arguments = $mixin->arguments;
		$block = $mixin->block;
		$attributes = $mixin->attributes;

		if ($mixin->call) {
            $code = $this->createCode("{$name}(%s);", $arguments);
			$this->buffer($code);
		}else{
            $code = $this->createCode("function {$name} (%s) {", $arguments);
			$this->buffer($code);
            $this->indents++;
			$this->visit($block);
            $this->indents--;
			$this->buffer($this->createCode('}'));
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

			$this->buffer($this->indent() . $open, false);
			$this->visitAttributes($tag->attributes);
			$this->buffer($close . $this->newline(), false);
		}else{
			$html_tag = '';

			if ($self_closing) {
				$html_tag = '<' . $tag->name . ' ' . (($this->terse) ? '>' : '/>');
			}else{
				$html_tag = '<' . $tag->name . '>';
			}

			$this->buffer($html_tag);
		}

		if (!$self_closing) {
			$this->indents++;
            if (isset($tag->code)) {
				$this->visitCode($tag->code);
            }
			$this->visit($tag->block);
			$this->indents--;

            $this->buffer('</'. $tag->name . '>');
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
		$this->buffer($str);
    }

    protected function visitText(Nodes\Text $text) {
		$this->buffer($this->interpolate($text->value));
    }

    protected function visitComment(Nodes\Comment $comment) {
		if (!$comment->buffer) {
			return;
		}

		$this->buffer('<!--' . $comment->value . '-->');
    }

	protected function visitBlockComment(Nodes\BlockComment $comment) {
		if (!$comment->buffer) {
			return;
		}

		if (0 === strpos('if', trim($comment->value))) {
			$this->buffer('<!--[' . trim($comment->value) . ']>');
			$this->visit($comment->block);
			$this->buffer('<![endif]-->');
		}else{
			$this->buffer('<!--' . $comment->value);
			$this->visit($comment->block);
			$this->buffer('-->');
		}
	}

    protected function visitCode(Nodes\Code $code) {

		if ($code->buffer) {

			if ($code->escape) {
				$this->buffer($this->createCode('echo htmlspecialchars(%s)',$code->value));
			}else{
				$this->buffer($this->createCode('echo %s',$code->value));
			}
		}else{

			// fix else, it needs to be in the same php block that closes the if
			$end = false;
			$index = count($this->buffer)-1;
			if (false !== strpos($this->buffer[$index], $this->createCode('}'))) {
				unset($this->buffer[$index]);
				$this->buffer($this->createCode('} %s {',$code->value));
			}else{
				$this->buffer($this->createCode('%s {',$code->value));
			}

		}

		if (isset($code->block)) {
			$this->indents++;
			$this->visit($code->block);
			$this->indents--;

			if (!$code->buffer) {
				$this->buffer($this->createCode('}'));
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

		$this->buffer($code);

		$this->indents++;
		$this->visit($node->block);
		$this->indents--;

		$this->buffer($this->createCode('}'));
	}
    
    protected function visitAttributes($attributes) {
        $items = array();
        $classes = array();

        /* Moved the string logic into createCode()
         * //if we have a concatenation we need to add the dollar sign and echo
        $attributeCode = function($attribute) {
            // explanation of the regular expression in the isConstant method
            preg_match_all('/([\'"])(?:\\\\.|[^\'"\\\\])*\1/', $attribute, $matches, PREG_SET_ORDER);

            $code = '';
            foreach ($matches as $m) {
                $pos = strpos($attribute, $m[0]);
                $str = trim($m[0],'\'"');

                if ($pos>0) {
                    $c = trim(mb_substr($attribute,0, $pos),' +');
                    if (mb_strlen($c)) {
                        $code .= $this->createCode('echo %s', $c);
                    }
                    $code .= $str;
                    $attribute = mb_substr($attribute,$pos+mb_strlen($m[0]));
                }else{
                    $code .= $str;
                    $attribute = mb_substr($attribute,$pos+mb_strlen($m[0]));
                }
            }

            if (mb_strlen($attribute)) {
                $code .= $this->createCode('echo %s', trim($attribute,' +'));
            }

            return $code;
        };*/

        foreach ($attributes as $attr) {
			$key = trim($attr['name']);
			$value = trim($attr['value']);

            /* createCode() is handling strings better
            if (false !== strpos($value, '+')) {
                $value = $attributeCode($value);
            }else{
                $value = trim($value, '\'"');
            }
            */
            if ($this->isConstant($value)) {
                $value = trim($value,' \'"');
            }else{
                $value = $this->createCode('echo %s', $value);
            }

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

		$this->buffer(implode(' ', $items), false);
    }
}
