<?php
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	public function candidate($source, $found_tokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->leftTokenIs([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST])) {
						$context_stack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$context_stack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($context_stack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($context_stack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prev_token_idx = $this->leftUsefulTokenIdx();
							list($tkn_id, $tkn_text) = $this->getToken($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_BRACKET_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						} elseif (self::ST_SHORT_ARRAY_OPEN == end($context_stack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prev_token_idx = $this->leftUsefulTokenIdx();
							list($tkn_id, $tkn_text) = $this->getToken($this->tkns[$prev_token_idx]);
							$this->tkns[$prev_token_idx] = [$tkn_id, rtrim($tkn_text, ',')];
						}
						array_pop($context_stack);
					}
					break;
				case T_STRING:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && T_ARRAY == end($context_stack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
						array_pop($context_stack);
						$context_stack[] = self::EMPTY_ARRAY;
					} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
						$context_stack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						if (T_ARRAY == end($context_stack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prev_token_idx = $this->leftUsefulTokenIdx();
							list($tkn_id, $tkn_text) = $this->getToken($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_PARENTHESES_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						} elseif (T_ARRAY == end($context_stack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prev_token_idx = $this->leftUsefulTokenIdx();
							list($tkn_id, $tkn_text) = $this->getToken($this->tkns[$prev_token_idx]);
							$this->tkns[$prev_token_idx] = [$tkn_id, rtrim($tkn_text, ',')];
						}
						array_pop($context_stack);
					}
					break;
			}
			$this->tkns[$this->ptr] = [$id, $text];
		}
		return $this->renderLight();
	}
}