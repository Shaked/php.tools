<?php
class LaravelStyle extends AdditionalPass {
	private $found_tokens;
	public function candidate($source, $found_tokens) {
		$this->found_tokens = $found_tokens;
		return true;
	}

	public function format($source) {
		$source = $this->namespace_merge_with_open_tag($source);
		$source = $this->allman_style_braces($source);
		$source = (new RTrim())->format($source);

		$fmt = new TightConcat();
		if ($fmt->candidate($source, $this->found_tokens)) {
			$source = $fmt->format($source);
		}
		return $source;
	}

	private function namespace_merge_with_open_tag($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	private function allman_style_braces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					if ($this->hasLn($text) && false !== strpos($text, $this->indent_char)) {
						$max_detected_indent = 0;
						$current_detected_indent = 0;
						$len = strlen($text);
						for ($i = 0; $i < $len; ++$i) {
							if ($this->new_line == $text[$i]) {
								$max_detected_indent = max($max_detected_indent, $current_detected_indent);
								$current_detected_indent = 0;
							}
							if ($this->indent_char == $text[$i]) {
								++$current_detected_indent;
							}
						}
						$max_detected_indent = max($max_detected_indent, $current_detected_indent);
					}
					$this->appendCode($text);
					break;
				case ST_CURLY_OPEN:
					if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY])) {
						list($prev_id, $prev_text) = $this->getToken($this->leftToken());
						if (!$this->hasLn($prev_text)) {
							$this->appendCode($this->getCrlf() . $this->getIndent($max_detected_indent));
						}
					}
					$this->appendCode($text);
					break;
				case T_ELSE:
				case T_ELSEIF:
				case T_FINALLY:
					list($prev_id, $prev_text) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prev_text)) {
						$this->appendCode($this->getCrlf() . $this->getIndent($max_detected_indent));
					}
					$this->appendCode($text);
					break;
				case T_CATCH:
					if (' ' == substr($this->code, -1, 1)) {
						$this->code = substr($this->code, 0, -1);
					}
					list($prev_id, $prev_text) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prev_text)) {
						$this->appendCode($this->getCrlf() . $this->getIndent($max_detected_indent));
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Applies Laravel Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php namespace A;

class A {
	function b()
	{
		if($a)
		{
			noop();
		}
		else
		{
			noop();
		}
	}

}
?>
EOT;
	}
}
