<?php
/**
 * From PHP-CS-Fixer
 */
class ShortArray extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (T_WHITESPACE == $id) {
							$this->append_code($text, false);
							continue;
						}

						if (ST_PARENTHESES_OPEN == $id) {
							++$paren_count;
							$this->append_code(ST_BRACKET_OPEN, false);
							continue;
						} elseif (ST_PARENTHESES_CLOSE == $id) {
							--$paren_count;
							$this->append_code(ST_BRACKET_CLOSE, false);
							continue;
						}

						if (0 == $paren_count) {
							break;
						}
					}
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}
