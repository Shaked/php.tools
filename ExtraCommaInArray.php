<?php
final class ExtraCommaInArray extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_STRING:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						array_unshift($context_stack, T_STRING);
					}
					$this->append_code($text, false);
					break;
				case T_ARRAY:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						array_unshift($context_stack, T_ARRAY);
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_shift($context_stack);
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						array_shift($context_stack);
					}
					$this->append_code($text, false);
					break;
				default:
					if (isset($context_stack[0]) && T_ARRAY === $context_stack[0] && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_shift($context_stack);
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