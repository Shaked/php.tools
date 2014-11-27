<?php
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	public function format($source) {
		$this->tkns = token_get_all($source);

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->left_token_is([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE])) {
						$context_stack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$context_stack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($context_stack[0]) && !$this->left_token_is(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($context_stack) && $this->has_ln_before() && !$this->left_useful_token_is(ST_COMMA)) {
							$prev_token_idx = $this->left_useful_token(true);
							list($tkn_id, $tkn_text) = $this->get_token($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_BRACKET_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						}
						array_pop($context_stack);
					}
					break;
				case T_STRING:
					if ($this->right_token_is(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->right_token_is(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && T_ARRAY == end($context_stack) && $this->right_token_is(ST_PARENTHESES_CLOSE)) {
						array_pop($context_stack);
					} elseif (!$this->left_token_is([T_ARRAY, T_STRING])) {
						$context_stack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						if (T_ARRAY == end($context_stack) && ($this->has_ln_left_token() || $this->has_ln_before()) && !$this->left_useful_token_is(ST_COMMA)) {
							$prev_token_idx = $this->left_useful_token(true);
							list($tkn_id, $tkn_text) = $this->get_token($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_PARENTHESES_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						}
						array_pop($context_stack);
					}
					break;

			}
		}
		return $this->render();
	}
}