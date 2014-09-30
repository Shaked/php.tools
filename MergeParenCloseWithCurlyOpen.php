<?php
final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($text, true);
					} elseif ($this->is_token(array(T_ELSE, T_STRING), true)) {
						$this->append_code($text, true);
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_ELSE:
				case T_ELSEIF:
					if ($this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($text, true);
					} else {
						$this->append_code($text, false);
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
