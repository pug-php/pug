<?php


function jade_text($bytes) {
	$patterns = array('/&(?!\w+;)/', '/</', '/>/', '/"/');
	$replacements = array('&amp;', '&lt;', '&gt;', '&quot;');
	return preg_replace($patterns, $replacements, $bytes);
}

function jade_html($bytes) {
	return $bytes;
}

class Lexer extends JP {

    protected $deferredObjects   = array();

    protected $lastIndents      = 0;

/* need? */    protected $lineno           = 1;

    protected $stash            = array();

    /**
     * Set lexer input.
     *
     * @param   string  $input  input string
     */
    public function setInput($input) {
		$this->adjustLineDelimiter($input);
/* TODO TAB */        $this->page            = preg_replace(array('/\r\n|\r/', '/\t/'), array("\n", '  '), $input);
        $this->deferredObjects  = array();
        $this->lastIndents      = 0;
        $this->lineno           = 1;
        $this->stash            = array();
    }

    /**
     * Return next token or previously stashed one.
     *
     * @return  Object
     */
    public function getAdvancedToken() {
        if ( $token = $this->getStashedToken() ) {
            return $token;
        }

        return $this->getNextToken();
    }

    /**
     * Defer token.
     *
     * @param   Object   $token  token to defer
     */
    public function deferToken(\stdClass $token) {
        $this->deferredObjects[] = $token;
    }

    /**
     * Predict for number of tokens.
     *
     * @param   integer     $number number of tokens to predict
     *
     * @return  Object              predicted token
     */
    public function predictToken($number = 1) {
        $fetch = $number - count($this->stash);

        while ( $fetch-- > 0 ) {
            $this->stash[] = $this->getNextToken();
        }

        return $this->stash[--$number];
    }

    /**
     * Construct token with specified parameters.
     *
     * @param   string  $type   token type
     * @param   string  $value  token value
     *
     * @return  Object          new token object
     */
    public function takeToken($type, $value = null) {
        return (Object) array(
            'type'  => $type
          , 'line'  => $this->lineno
          , 'value' => $value
        );
    }

    /**
     * Return stashed token.
     *
     * @return  Object|boolean   token if has stashed, false otherways
     */
    protected function getStashedToken() {
        return count($this->stash) ? array_shift($this->stash) : null;
    }

    /**
     * Return deferred token.
     *
     * @return  Object|boolean   token if has deferred, false otherways
     */
    protected function getDeferredToken() {
        return count($this->deferredObjects) ? array_shift($this->deferredObjects) : null;
    }

    /**
     * Return next token.
     *
     * @return  Object
     */
    protected function getNextToken() {
        $scanners = array(
            'getDeferredToken'
          , 'scanEOS'
          , 'TAG'
          , 'FILTER'
          , 'scanCode'
          , 'DOCUMENT_TYPE'
          , 'ID'
          , 'CLASS'
          , 'scanAttributes'
          , 'scanDotBlock'
          , 'scanIndentation'
          , 'scanComment'
          , 'TEXT'
        );

        foreach ( $scanners as $scan ) {
			if (preg_match('/^scan|get/', $scan)) {
				$token = $this->$scan();
			} else {
				$token = $this->next($scan);
			}

            if ( $token ) {
                return $token;
            }
        }
    }
		
    protected function scanDotBlock() {
			if ( $this->page[0] === '.' ) {
				$token = $this->takeToken('text', $this->next('dotBlock')->value);
				return $token;
			}
			else return null;
    }

    /**
     * Scan EOS from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanEOS() {
        if ( mb_strlen($this->page) ) {
            return;
        }

        return $this->lastIndents-- > 0 ? $this->takeToken('outdent') : $this->takeToken('eos');
    }

    /**
     * Scan comment from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanComment() {
        $matches = array();

        if ( preg_match('/^ *\/\/(-)?([^\n]+)?/', $this->page, $matches) ) {
            $this->reduce($matches[0]);
            $token = $this->takeToken('comment', isset($matches[2]) ? $matches[2] : '');
            $token->buffer = !isset($matches[1]) || '-' !== $matches[1];

            return $token;
        }
    }

    protected function scanCode() {
        $matches = array();

        if ( preg_match('/^(!?=|-)([^\n]+)/', $this->page, $matches) ) {
            $this->reduce($matches[0]);

            $flags = $matches[1];
            $token = $this->takeToken('code', $matches[2]);
            $token->buffer = (isset($flags[0]) && '=' === $flags[0]) || (isset($flags[1]) && '=' === $flags[1]);
			$token->code_type = $matches[1];

            return $token;
        }
    }

    /**
     * Scan attributes from input & return them if found.
     *
     * @return  Object|null
     */
    protected function scanAttributes() {
        if ( $this->page[0] === '(' ) {
            $token = $this->takeToken('attributes', '');
            $token->attributes = array();
			//pass '('
			$this->next('ATTRIBUTE');

			do {
				$attr = $this->next('ATTRIBUTE');
				if ($attr[0] == '') {
					//TODO Error reporting
					return null;
				}
				$attr = array_values(preg_grep('/^.+$/', $attr));

				if (isset($attr[1]) && isset($attr[2])) {
					$token->attributes[$attr[1]] = $attr[2];
				} elseif (isset($attr[1]) && $attr[1] != ',' && $attr[1] != ')') {
					$token->attributes[$attr[1]] = '"'.$attr[1].'"';
				}
			} while ($attr[1] != ')');
            return $token;
        }
    }

    protected function scanIndentation() {
        $matches = array();

        if ( preg_match('/^\n( *)/', $this->page, $matches) ) {
            $this->lineno++;
            $this->reduce($matches[0]);

            $token      = $this->takeToken('indent', $matches[1]);
            $indents    = mb_strlen($token->value) / 2;


            if ( mb_strlen($this->page) && $this->page[0] === "\n" ) {
                $token->type = 'newline';

                return $token;
            } elseif ( $indents % 1 !== 0 ) {
                throw new \Exception(sprintf(
                    'Invalid indentation found. Spaces count must be a multiple of two, but %d got.'
                  , mb_strlen($token->value)
                ));
            } elseif ( $indents === $this->lastIndents ) {
                $token->type = 'newline';
            } elseif ( $indents > $this->lastIndents + 1 ) {
                throw new \Exception(sprintf(
                    'Invalid indentation found. Got %d, but expected %d.'
                  , $indents
                  , $this->lastIndents + 1
                ));
            } elseif ( $indents < $this->lastIndents ) {
                $count = $this->lastIndents - $indents;
                $token->type = 'outdent';
                while ( --$count ) {
                    $this->deferToken($this->takeToken('outdent'));
                }
            }

            $this->lastIndents = $indents;

            return $token;
        }
    }
}



?>
