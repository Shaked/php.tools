<?php
final class PSR2CurlyOpenNextLine extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->indent_char = '    ';
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_START_HEREDOC:
					$this->append_code($text);
					$this->print_until(T_END_HEREDOC);
					break;
				case ST_QUOTE:
					$this->append_code($text);
					$this->print_until_the_end_of_string();
					break;
				case T_INTERFACE:
				case T_TRAIT:
				case T_CLASS:
					$this->append_code($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN === $id) {
							$this->append_code($this->get_crlf_indent());
							prev($this->tkns);
							break;
						} else {
							$this->append_code($text);
						}
					}
					break;
				case T_FUNCTION:
					if (!$this->left_token_is([T_DOUBLE_ARROW, T_RETURN, ST_EQUAL, ST_PARENTHESES_OPEN, ST_COMMA]) && $this->right_useful_token_is(T_STRING)) {
						$this->append_code($text);
						$touched_ln = false;
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							if (T_WHITESPACE == $id && $this->has_ln($text)) {
								$touched_ln = true;
							}
							if (ST_CURLY_OPEN === $id && !$touched_ln) {
								$this->append_code($this->get_crlf_indent());
								prev($this->tkns);
								break;
							} elseif (ST_CURLY_OPEN === $id) {
								prev($this->tkns);
								break;
							} else {
								$this->append_code($text);
							}
						}
						break;
					} else {
						$this->append_code($text);
					}
					break;
				case ST_CURLY_OPEN:
					$this->append_code($text);
					$this->set_indent(+1);
					break;
				case ST_CURLY_CLOSE:
					$this->set_indent(-1);
					$this->append_code($text);
					break;
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}