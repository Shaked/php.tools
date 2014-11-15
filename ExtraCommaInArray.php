<?php
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->is_token([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE], true)) {
						$context_stack[] = self::ST_SHORT_ARRAY_OPEN;
					}
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_STRING;
					}
					$this->append_code($text, false);
					break;
				case T_ARRAY:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_ARRAY;
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_pop($context_stack);
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						array_pop($context_stack);
					}
					$this->append_code($text, false);
					break;
				default:
					if (T_WHITESPACE != $id && self::ST_SHORT_ARRAY_OPEN === end($context_stack) && $this->is_token(ST_BRACKET_CLOSE)) {
						array_pop($context_stack);
						if (ST_COMMA === $id || T_END_HEREDOC === $id || T_COMMENT === $id || T_DOC_COMMENT === $id || !$this->has_ln_after()) {
							$this->append_code($text, false);
						} else {
							$this->append_code($text . ',', false);
						}
						break;
					} elseif (T_WHITESPACE != $id && T_ARRAY === end($context_stack) && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_pop($context_stack);
						if (ST_COMMA === $id || T_END_HEREDOC === $id || T_COMMENT === $id || T_DOC_COMMENT === $id || !$this->has_ln_after()) {
							$this->append_code($text, false);
						} else {
							$this->append_code($text . ',', false);
						}
						break;
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}
		return $this->code;
	}
}