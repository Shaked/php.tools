<?php
final class YodaComparisons extends FormatterPass {
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function format($source) {
		return $this->yodise($source);
	}
	protected function yodise($source) {
		$tkns = $this->aggregate_variables($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			if (is_null($token)) {
				continue;
			}
			list($id, $text) = $this->get_token($token);
			switch ($id) {
				case T_IS_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
					list($left, $right) = $this->siblings($tkns, $ptr);
					list($left_id, $left_text) = $tkns[$left];
					list($right_id, $right_text) = $tkns[$right];
					if ($left_id == $right_id) {
						continue;
					}

					$left_pure_variable = $this->is_pure_variable($left_id);
					for ($leftmost = $left; $leftmost >= 0; --$leftmost) {
						list($left_scan_id, $left_scan_text) = $this->get_token($tkns[$leftmost]);
						if ($this->is_lower_precedence($left_scan_id)) {
							++$leftmost;
							break;
						}
						$left_pure_variable &= $this->is_pure_variable($left_scan_id);
					}

					$right_pure_variable = $this->is_pure_variable($right_id);
					for ($rightmost = $right; $rightmost < sizeof($tkns) - 1; ++$rightmost) {
						list($right_scan_id, $right_scan_text) = $this->get_token($tkns[$rightmost]);
						if ($this->is_lower_precedence($right_scan_id)) {
							--$rightmost;
							break;
						}
						$right_pure_variable &= $this->is_pure_variable($right_scan_id);
					}

					if ($left_pure_variable && !$right_pure_variable) {
						$orig_left_tokens = $left_tokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $leftmost, $left - $leftmost + 1)));
						$orig_right_tokens = $right_tokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $right, $rightmost - $right + 1)));

						$left_tokens = (substr($orig_right_tokens, 0, 1) == ' ' ? ' ' : '') . trim($left_tokens) . (substr($orig_right_tokens, -1, 1) == ' ' ? ' ' : '');
						$right_tokens = (substr($orig_left_tokens, 0, 1) == ' ' ? ' ' : '') . trim($right_tokens) . (substr($orig_left_tokens, -1, 1) == ' ' ? ' ' : '');

						$tkns[$leftmost] = ['REPLACED', $right_tokens];
						$tkns[$right] = ['REPLACED', $left_tokens];

						if ($leftmost != $left) {
							for ($i = $leftmost + 1; $i <= $left; ++$i) {
								$tkns[$i] = null;
							}
						}
						if ($rightmost != $right) {
							for ($i = $right + 1; $i <= $rightmost; ++$i) {
								$tkns[$i] = null;
							}
						}
					}
			}
		}
		return $this->render($tkns);
	}

	private function is_pure_variable($id) {
		return self::CHAIN_VARIABLE == $id || T_VARIABLE == $id || T_INC == $id || T_DEC == $id || ST_EXCLAMATION == $id || T_COMMENT == $id || T_DOC_COMMENT == $id || T_WHITESPACE == $id;
	}
	private function is_lower_precedence($id) {
		switch ($id) {
			case ST_REFERENCE:
			case ST_BITWISE_XOR:
			case ST_BITWISE_OR:
			case T_BOOLEAN_AND:
			case T_BOOLEAN_OR:
			case ST_QUESTION:
			case ST_COLON:
			case ST_EQUAL:
			case T_PLUS_EQUAL:
			case T_MINUS_EQUAL:
			case T_MUL_EQUAL:
			case T_POW_EQUAL:
			case T_DIV_EQUAL:
			case T_CONCAT_EQUAL:
			case T_MOD_EQUAL:
			case T_AND_EQUAL:
			case T_OR_EQUAL:
			case T_XOR_EQUAL:
			case T_SL_EQUAL:
			case T_SR_EQUAL:
			case T_DOUBLE_ARROW:
			case T_LOGICAL_AND:
			case T_LOGICAL_XOR:
			case T_LOGICAL_OR:
			case ST_COMMA:
			case ST_SEMI_COLON:
			case T_RETURN:
			case T_THROW:
			case T_GOTO:
			case T_CASE:
			case T_COMMENT:
			case T_DOC_COMMENT:
			case T_OPEN_TAG:
				return true;
		}
		return false;
	}

	private function aggregate_variables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initial_ptr = $ptr;
				$tmp = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'yodise');
				$tkns[$initial_ptr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initial_ptr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initial_ptr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initial_index = $ptr;
				$stack = $text;
				$touched_variable = false;
				if (T_VARIABLE == $id) {
					$touched_variable = true;
				}
				if (!$this->right_token_subset_is_at_idx(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					// if (ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id || ST_PARENTHESES_CLOSE == $id || ST_SEMI_COLON == $id ) {
					// 	$token = prev($tkns);
					// 	$ptr = key($tkns);
					// 	list($id, $text) = $this->get_token($token);
					// 	break;
					// }
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'yodise');
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'yodise');
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'yodise');
					}

					$stack .= $text;

					if (!$touched_variable && T_VARIABLE == $id) {
						$touched_variable = true;
					}

					if (
						!$this->right_token_subset_is_at_idx(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initial_index] = [self::CHAIN_FUNC, $stack];
				} elseif ($touched_variable) {
					$tkns[$initial_index] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initial_index] = [self::CHAIN_LITERAL, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}
}