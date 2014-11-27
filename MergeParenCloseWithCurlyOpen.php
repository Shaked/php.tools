<?php
final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->left_token_is([T_ELSE, T_STRING, ST_PARENTHESES_CLOSE])) {
						$this->rtrim_and_append_code($text);
					} else {
						$this->append_code($text);
					}
					break;
				case T_ELSE:
				case T_ELSEIF:
					if ($this->left_token_is(ST_CURLY_CLOSE)) {
						$this->rtrim_and_append_code($text);
					} else {
						$this->append_code($text);
					}
					break;
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}
